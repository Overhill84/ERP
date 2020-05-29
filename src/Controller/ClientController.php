<?php

namespace App\Controller;

use DateTime;
use DateTimeZone;
use App\Entity\Devis;
use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\DevisRepository;
use App\Repository\ClientRepository;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use CMEN\GoogleChartsBundle\GoogleCharts\Options\PieChart\PieSlice;

class ClientController extends AbstractController
{
    /**
     * @Route("/client/add", name="add_client")
     */
    public function add(Request $request, EntityManagerInterface $manager)
    {
        $client = new Client();

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);
        $client->setCreatedAt(new DateTime('NOW', new DateTimeZone('Europe/Paris')));

        if(($client->getType() == 'Particulier' && (!empty($client->getSociete()) || !empty($client->getSiret()))) || ($client->getType() == 'Professionnel' && empty($client->getSociete())))
        {
            $this->addFlash('erreur', ' Erreur, vérifiez vos champs.');
        }
        elseif ($form->isSubmitted() && $form->isValid()) {
            $manager->persist($client);
            $manager->flush();

            return $this->redirectToRoute('show_client');
        }

        return $this->render('client/addClient.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/client{id}/update", name="update_client")
     */
    public function update(Client $client, Request $request, EntityManagerInterface $manager)
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);
        
        if(($client->getType() == 'Particulier' && (!empty($client->getSociete()) || !empty($client->getSiret()))) || ($client->getType() == 'Professionnel' && empty($client->getSociete())))
        {
            $this->addFlash('erreur', ' Erreur, vérifiez vos champs.');
        }
        elseif ($form->isSubmitted() && $form->isValid()) {
            $manager->persist($client);
            $manager->flush();

            return $this->redirectToRoute('show_client', ['id' => $client->getId()]);
        }

        return $this->render('client/updateClient.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/clients", name="clients")
     */
    public function showAll(ClientRepository $clientRepo, PaginatorInterface $paginator, Request $request)
    {
        $datas = $clientRepo->findAll();

        $clients = $paginator->paginate(
            $datas,
            $request->query->getInt('page', 1),
            10
        );
        $proCount = $clientRepo->findBy(['type' => 'Professionnel' ]);
        $proCount = count($proCount);
        $partCount = $clientRepo->findBy(['type' => 'Particulier' ]);
        $partCount = count($partCount);

        $pieChart = new PieChart();
        $pieChart->getData()->setArrayToDataTable(
            [
                ['Type Client', 'Compte types'],
                ['Professionnels',     $proCount],
                ['Particuliers',      $partCount]
            ]
        );
        $pieChart->getOptions()->setTitle('Clients');
        $pieChart->getOptions()->setIs3D("true");
        $pieChart->getOptions()->setHeight(370);
        $pieChart->getOptions()->setWidth(500);
        $pieChart->getOptions()->getTitleTextStyle()->setBold(true);
        $pieChart->getOptions()->getTitleTextStyle()->setColor('#000000');
        $pieChart->getOptions()->getTitleTextStyle()->setItalic(true);
        $pieChart->getOptions()->getTitleTextStyle()->setFontName('Arial');
        $pieChart->getOptions()->getTitleTextStyle()->setFontSize(20);
        $pieSlice = new PieSlice();
        $pieSlice->setColor('#007bff');
        $pieSlice2 = new PieSlice();
        $pieSlice2->setColor('#00ffb2');
        $pieChart->getOptions()->setSlices([$pieSlice, $pieSlice2]);
        $pieChart->getOptions()->getPieSliceTextStyle()->setColor('black');

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
            'pie' => $pieChart
        ]);
    }

    /**
     * @Route("/client{id}", name="show_client")
     */
    public function showClient(Client $client, DevisRepository $devisRepo, FactureRepository $factureRepo)
    {
        $devis = $devisRepo->findBy(['client' => $client]);
        $factures = $factureRepo->findBy(['Client' => $client]);

        return $this->render('client/showClient.html.twig', [
            'client' => $client,
            'devis' => $devis,
            'factures' => $factures
        ]);
    }

    /**
     * @Route("/client", name="show_client_search")
     */
    public function showClientSearch(DevisRepository $devisRepo, FactureRepository $factureRepo, Request $request, ClientRepository $clientRepo)
    {
        $idClient = $request->query->get('clients');
        $client = $clientRepo->find($idClient);
        $devis = $devisRepo->findBy(['client' => $client]);
        $factures = $factureRepo->findBy(['Client' => $client]);

        return $this->render('client/showClient.html.twig', [
            'client' => $client,
            'devis' => $devis,
            'factures' => $factures
        ]);
    }

    public function allClientSearch()
    {
        $clients = $this->getDoctrine()
            ->getRepository(Client::class)
            ->findAll();
        return $clients;
    }

}
