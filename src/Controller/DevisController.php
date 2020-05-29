<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Devis;
use App\Entity\Client;
use App\Repository\DevisRepository;
use App\Repository\ClientRepository;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DevisController extends AbstractController
{
    /**
     * @Route("/client{id}/devis", name="create_devis")
     */
    public function createDevis(Client $client, ArticleRepository $artRepo, SessionInterface $session)
    {
        $listArticle = [];
        $quantites = [];
        $totalHT = 0;
        $totalTTC = 0;
        $holdPrix = 0;


        if (!empty($session->get('articles'))) {

            $listSess = $session->get('articles');

            // Gestion quantité
            $count = array_count_values($listSess);
            foreach ($count as $idArt => $qty) {
                $quantites[] = [
                    "id" => $idArt,
                    "qty" => $qty
                ];
            }

            $listSess = array_unique($listSess);

            foreach ($listSess as $article) {
                $art = $artRepo->findOneBy(['id' => $article]);

                array_push($listArticle, $art);

                foreach ($quantites as $qty) {
                    if ($qty['id'] == $article) {
                        $totalHT = $art->getPrix() * $qty['qty'];
                        $holdPrix += $totalHT;
                    }
                }
            }
            $totalHT = $holdPrix;
            $holdTaxe = $totalHT * 0.2;
            $totalTTC = $totalHT + $holdTaxe;
        }


        $articles = $artRepo->findAll();
        return $this->render('devis/createDevis.html.twig', [
            'client' => $client,
            'articles' => $articles,
            'listArticle' => $listArticle,
            'quantites' => $quantites,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC
        ]);
    }

    /**
     * @Route("/client{id}/devis/add/article", name="add_article_devis")
     */
    public function addArticleDevis(Client $client, Request $request, SessionInterface $session)
    {
        $idClient = $client->getId();

        $articles = $session->get('articles');

        $idArticle = $request->query->get('articleSelect');

        if (empty($articles)) {
            $articles = [$idArticle];
        } else {
            array_push($articles, $idArticle);
        }

        $session->set('articles', $articles);
        return $this->redirectToRoute('create_devis', ['id' => $idClient]);
    }

    /**
     * @Route("/client{id}/devis/valid", name="valid_devis")
     */
    public function validDevis(Client $client, SessionInterface $session, ArticleRepository $artRepo, EntityManagerInterface $manager)
    {
        $quantites = [];
        $holdPrix = 0;

        if (!empty($session->get('articles'))) {

            $listSess = $session->get('articles');

            // Gestion quantité
            $count = array_count_values($listSess);
            foreach ($count as $idArt => $qty) {
                $quantites[] = [
                    "id" => $idArt,
                    "qty" => $qty
                ];
            }

            $listSess = array_unique($listSess);

            $devis = new Devis();
            $devis->setClient($client);

            foreach ($listSess as $produit) {
                $newArticle = $artRepo->findOneBy(['id' => $produit]);
                foreach ($quantites as $qty) {
                    if ($qty['id'] == $newArticle->getId()) {

                        for ($i = 0; $i < $qty['qty']; $i++) {
                            $devis->addProduit($newArticle);
                        }
                    }
                }
                foreach ($quantites as $qty) {
                    if ($qty['id'] == $produit) {
                        $totalHT = $newArticle->getPrix() * $qty['qty'];
                        $holdPrix += $totalHT;
                    }
                }
            }
            $totalHT = $holdPrix;
            $devis->setTotalHt($totalHT);
        }
        $manager->persist($devis);
        $manager->flush();
        $session->remove('articles');
        return $this->redirectToRoute('show_client', ['id' => $client->getId()]);
    }

    /**
     * @Route("/client{id}/devis/abort", name="abort_devis")
     */
    public function abortDevis(Client $client, SessionInterface $session)
    {
        $session->remove('articles');
        return $this->redirectToRoute('show_client', ['id' => $client->getId()]);
    }

    /**
     * @Route("/client/devis{id}", name="show_devis")
     */
    public function showDevis(Devis $dev, ClientRepository $clientRepo, EntityManagerInterface $manager, ArticleRepository $artRepo)
    {
        $conn = $manager->getConnection();

        $client = $dev->getClient();
        $client = $clientRepo->findOneBy(['id' => $client->getId()]);
        $idDev = $dev->getId();

        $sql = "SELECT * FROM devis_article WHERE devis_id = $idDev";
        $request = $conn->prepare($sql);
        $request->execute();
        $datas = $request->fetchAll();

        $articles = [];
        $listArticle = [];
        foreach ($datas as $data) {
            array_push($articles, $data['article_id']);
        }

        $count = array_count_values($articles);
        foreach ($count as $idArt => $qty) {
            $quantites[] = [
                "id" => $idArt,
                "qty" => $qty
            ];
        }
        $articles = array_unique($articles);

        foreach ($articles as $article) {
            $art = $artRepo->findOneBy(['id' => $article]);

            array_push($listArticle, $art);
        }
        $holdTaxe = $dev->getTotalHt() * 0.2;
        $totalTTC = $dev->getTotalHt() + $holdTaxe;

        return $this->render('devis/showDevis.html.twig', [
            'client' => $client,
            'articles' => $listArticle,
            'quantites' => $quantites,
            'id_devis' => $idDev,
            'totalHT' => $dev->getTotalHt(),
            'totalTTC' => $totalTTC
        ]);
    }

    /**
     * @Route("/devis", name="show_all_devis")
     */
    public function showAllDevis(DevisRepository $dev, PaginatorInterface $paginator, Request $request)
    {
        $datas = $dev->findAll();
        $devis = $paginator->paginate(
            $datas,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('devis/index.html.twig', [
            'devis' => $devis
        ]);
    }
    /**
     * @Route("/client/devis{id}/pdf", name="pdf_devis")
     */
    public function devisPdf(Devis $dev, ClientRepository $clientRepo, EntityManagerInterface $manager, ArticleRepository $artRepo, \Knp\Snappy\Pdf $snappy)
    {
        $conn = $manager->getConnection();

        $client = $dev->getClient();
        $client = $clientRepo->findOneBy(['id' => $client->getId()]);
        $idDev = $dev->getId();

        $sql = "SELECT * FROM devis_article WHERE devis_id = $idDev";
        $request = $conn->prepare($sql);
        $request->execute();
        $datas = $request->fetchAll();

        $articles = [];
        $listArticle = [];
        foreach ($datas as $data) {
            array_push($articles, $data['article_id']);
        }

        $count = array_count_values($articles);
        foreach ($count as $idArt => $qty) {
            $quantites[] = [
                "id" => $idArt,
                "qty" => $qty
            ];
        }
        $articles = array_unique($articles);

        foreach ($articles as $article) {
            $art = $artRepo->findOneBy(['id' => $article]);

            array_push($listArticle, $art);
        }
        $holdTaxe = $dev->getTotalHt() * 0.2;
        $totalTTC = $dev->getTotalHt() + $holdTaxe;

        $this->snappy = $snappy;

        $html = $this->renderView('devis/pdfDevis.html.twig', array(
            'client' => $client,
            'articles' => $listArticle,
            'quantites' => $quantites,
            'totalHT' => $dev->getTotalHt(),
            'totalTTC' => $totalTTC
        ));

        return new PdfResponse(
            $snappy->getOutputFromHtml($html),
            'devis-'.$idDev.'.pdf'
        );

        return $this->redirectToRoute('show_devis', ['id' => $idDev]);
    }
}

