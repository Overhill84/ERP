<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ArticleController extends AbstractController
{
    /**
     * @Route("/article", name="index_article")
     */
    public function indexArticle(Request $request, EntityManagerInterface $manager, ArticleRepository $artRepo)
    {
        $article = new Article();

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $manager->persist($article);
            $manager->flush();

            return $this->redirectToRoute('index_article');
        }

        $articles = $artRepo->findAll();

        return $this->render('article/indexArticles.html.twig', [
            'form' => $form->createView(),
            'articles' => $articles
        ]);
    }

    /**
     * @Route("/article/{id}", name="show_article")
     */
    public function showArticle(Article $article)
    {
        return $this->render('article/article.html.twig', [
            'article' => $article
        ]);
    }
}
