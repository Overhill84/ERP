<?php

namespace App\Controller;


use App\Entity\Devis;
use App\Entity\Client;
use App\Entity\Facture;
use App\Repository\DevisRepository;
use App\Repository\ClientRepository;
use App\Repository\ArticleRepository;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FacturationController extends AbstractController
{
    /**
     * @Route("/client{id}/facture", name="create_facture")
     */
    public function createFacture(Client $client, ArticleRepository $artRepo, SessionInterface $session)
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
        return $this->render('facture/createFacture.html.twig', [
            'client' => $client,
            'articles' => $articles,
            'listArticle' => $listArticle,
            'quantites' => $quantites,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC
        ]);
    }

    /**
     * @Route("/client{id}/facture/add/article", name="add_article_facture")
     */
    public function addArticleFacture(Client $client, Request $request, SessionInterface $session)
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
        return $this->redirectToRoute('create_facture', ['id' => $idClient]);
    }

    /**
     * @Route("/client{id}/facture/valid", name="valid_facture")
     */
    public function validFacture(Client $client, SessionInterface $session, ArticleRepository $artRepo, EntityManagerInterface $manager)
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

            $facture = new Facture();
            $facture->setClient($client);

            foreach ($listSess as $produit) {
                $newArticle = $artRepo->findOneBy(['id' => $produit]);
                foreach ($quantites as $qty) {
                    if ($qty['id'] == $newArticle->getId()) {

                        for ($i = 0; $i < $qty['qty']; $i++) {
                            $facture->addArticle($newArticle);
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
            $facture->setTotalHt($totalHT);
        }
        $manager->persist($facture);
        $manager->flush();
        $session->remove('articles');
        return $this->redirectToRoute('show_client', ['id' => $client->getId()]);
    }

    /**
     * @Route("/client{id}/facture/abort", name="abort_facture")
     */
    public function abortFacture(Client $client, SessionInterface $session)
    {
        $session->remove('articles');
        return $this->redirectToRoute('show_client', ['id' => $client->getId()]);
    }

    /**
     * @Route("/client/facture{id}", name="show_facture")
     */
    public function showFacture(Facture $fact, ClientRepository $clientRepo, EntityManagerInterface $manager, ArticleRepository $artRepo)
    {
        $conn = $manager->getConnection();

        $client = $fact->getClient();
        $client = $clientRepo->findOneBy(['id' => $client->getId()]);
        $idFac = $fact->getId();

        $sql = "SELECT * FROM facture_article WHERE facture_id = $idFac";
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
        $holdTaxe = $fact->getTotalHt() * 0.2;
        $totalTTC = $fact->getTotalHt() + $holdTaxe;

        return $this->render('facture/showFacture.html.twig', [
            'client' => $client,
            'articles' => $listArticle,
            'quantites' => $quantites,
            'id_facture' => $idFac,
            'totalHT' => $fact->getTotalHt(),
            'totalTTC' => $totalTTC
        ]);
    }

    /**
     * @Route("/factures", name="show_all_factures")
     */
    public function showAllFactures(FactureRepository $fac, PaginatorInterface $paginator, Request $request)
    {
        $datas = $fac->findAll();

        $factures = $paginator->paginate(
            $datas,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('facture/index.html.twig', [
            'factures' => $factures
        ]);
    }

    /**
     * @Route("/client/facture{id}/pdf", name="pdf_facture")
     */
    public function facturePdf(Facture $fact, ClientRepository $clientRepo, EntityManagerInterface $manager, ArticleRepository $artRepo, \Knp\Snappy\Pdf $snappy)
    {
        $conn = $manager->getConnection();

        $client = $fact->getClient();
        $client = $clientRepo->findOneBy(['id' => $client->getId()]);
        $idFac = $fact->getId();

        $sql = "SELECT * FROM facture_article WHERE facture_id = $idFac";
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
        $holdTaxe = $fact->getTotalHt() * 0.2;
        $totalTTC = $fact->getTotalHt() + $holdTaxe;

        $this->snappy = $snappy;

        $html = $this->renderView('facture/pdfFacture.html.twig', array(
            'client' => $client,
            'articles' => $listArticle,
            'quantites' => $quantites,
            'id_facture' => $idFac,
            'totalHT' => $fact->getTotalHt(),
            'totalTTC' => $totalTTC
        ));

        return new PdfResponse(
            $snappy->getOutputFromHtml($html),
            'facture-'.$idFac.'.pdf'
        );

        return $this->redirectToRoute('show_facture', ['id' => $idFac]);
    }
}