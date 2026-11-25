<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Nom et adresse du client (pré-remplis à chaque commande). L'email ne se modifie pas ici. */
class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom et prénom', 'attr' => ['autocomplete' => 'name'], 'constraints' => [new Assert\NotBlank(message: 'Indiquez votre nom.')]])
            ->add('adresse', TextType::class, ['label' => 'Adresse', 'required' => false, 'attr' => ['autocomplete' => 'street-address']])
            ->add('codePostal', TextType::class, ['label' => 'Code postal', 'required' => false, 'attr' => ['autocomplete' => 'postal-code', 'inputmode' => 'numeric']])
            ->add('ville', TextType::class, ['label' => 'Ville', 'required' => false, 'attr' => ['autocomplete' => 'address-level2']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Utilisateur::class]);
    }
}
