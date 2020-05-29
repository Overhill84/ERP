<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'type',
                ChoiceType::class,
                ['choices' => [
                    'Particulier' => 'Particulier',
                    'Professionnel' => 'Professionnel'
                ]])
            ->add('nom', TextType::class, ['label' => 'Nom :'])
            ->add('prenom', TextType::class, ['label' => 'Prénom :'])
            ->add('societe', TextType::class, ['label' => 'Nom société :', 'required' => false,])
            ->add('siret', NumberType::class, ['label' => 'SIRET :', 'required' => false,])
            ->add('telephone', TelType::class, ['label' => 'Téléphone :'])
            ->add('mail', EmailType::class, ['label' => 'E-mail :', 'required' => false,])
            ->add('adresse', TextType::class, ['label' => 'Adresse :', 'required' => false,])
            ->add('codePostal', NumberType::class, ['label' => 'Code postal :', 'required' => false,])
            ->add('ville', TextType::class, ['label' => 'Ville :', 'required' => false,])
            ->add('note', TextareaType::class, ['label' => 'Note :', 'required' => false,])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
