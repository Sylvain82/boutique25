<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Coordonnées demandées au client avant le paiement. */
class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom et prénom', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['autocomplete' => 'email']])
            ->add('adresse', TextType::class, ['label' => 'Adresse', 'attr' => ['autocomplete' => 'street-address']])
            ->add('codePostal', TextType::class, ['label' => 'Code postal', 'attr' => ['autocomplete' => 'postal-code', 'inputmode' => 'numeric']])
            ->add('ville', TextType::class, ['label' => 'Ville', 'attr' => ['autocomplete' => 'address-level2']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Commande::class]);
    }
}
