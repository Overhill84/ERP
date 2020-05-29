<?php

namespace App\Controller;

use DateTime;
use DateTimeZone;
use App\Entity\Client;
use App\Entity\Ticket;
use App\Form\TicketType;
use App\Repository\ClientRepository;
use App\Repository\TicketRepository;
use Symfony\Component\Form\FormBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormBuilderInterface;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TicketController extends AbstractController
{

    /**
     * @Route("/ticket", name="ticket_index")
     */
    public function index(TicketRepository $ticketRepo, ClientRepository $clientRepo, PaginatorInterface $paginator, Request $request)
    {
        $datas = $ticketRepo->findBy([], ['createdAt' => 'DESC']);
        $clients = $clientRepo->findAll();

        $tickets = $paginator->paginate(
            $datas,
            $request->query->getInt('page', 1),
            4
        );

        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets,
            'clients' => $clients,
        ]);
    }

    /**
     * @Route("/ticket/add{id}", name="ticket_add_client")
     */
    public function addClient(ClientRepository $clientRepo, Client $client, Request $request, EntityManagerInterface $manager)
    {
        $client = $clientRepo->find($client);
        $ticket = new Ticket();

        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);
        $ticket->setClient($client);
        $ticket->setCreatedAt(new DateTime('NOW', new DateTimeZone('Europe/Paris')));
        $ticket->setEtat('Ouvert');

        if ($form->isSubmitted() && $form->isValid()) {
             $manager->persist($ticket);
             $manager->flush();

             return $this->redirectToRoute('ticket_index');
        }

        return $this->render('ticket/addTicket.html.twig', [
            'client' => $client,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/ticket/choix", name="ticket_choix")
     */
    public function choixClient(Request $request)
    {
        $id = $request->query->get('clients');
        
        return $this->redirectToRoute('ticket_add_client',  ['id' => $id]);
    }

    /** 
     * @Route("/ticket{id}", name="ticket_show")
     */
    public function showDevis(Ticket $ticket, ClientRepository $clientRepo)
    {
        $client = $ticket->getClient();
        $client = $clientRepo->findOneBy(['id' => $client->getId()]);
        
        return $this->render('ticket/showTicket.html.twig', [
            'client' => $client,
            'ticket' => $ticket
        ]);
    }

    /** 
     * @Route("/ticket{id}/close", name="ticket_close")
     */
    public function closeDevis(Ticket $ticket, Request $request, EntityManagerInterface $manager)
    {
        $id = $ticket->getId();
        
        $resol = $request->query->get('resolution');
        if(!empty($resol))
        {
            $ticket->setResolution($resol);
            $ticket->setEtat('Fermé');
            $ticket->setClosedAt(new DateTime('NOW', new DateTimeZone('Europe/Paris')));
            $manager->persist($ticket);
            $manager->flush();

            return $this->redirectToRoute('ticket_show',  ['id' => $id]);
        }
    }
}
