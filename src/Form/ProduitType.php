<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** Formulaire d'ajout / modification d'un produit (administration). */
class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('marque', TextType::class, ['label' => 'Marque'])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'help' => 'Ex. : Casque audio, Clavier… (sert au filtre de la liste)',
                'attr' => ['list' => 'types-existants'],
            ])
            ->add('prix', IntegerType::class, ['label' => 'Prix (€)', 'attr' => ['min' => 1]])
            ->add('promotion', CheckboxType::class, ['label' => 'En promotion', 'required' => false])
            // Photo : non liée directement à l'entité, le contrôleur la réduit et l'enregistre (service ImageProduit)
            ->add('image', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'help' => 'JPEG, PNG ou WebP, 8 Mo maximum. Elle est réduite automatiquement.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [new Image(
                    maxSize: '8M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    mimeTypesMessage: 'Formats acceptés : JPEG, PNG ou WebP.',
                    maxSizeMessage: 'La photo est trop lourde (8 Mo maximum).',
                    maxPixels: 40_000_000,
                    maxPixelsMessage: 'La photo est trop grande (40 millions de pixels maximum).',
                )],
            ]);

        $produit = $builder->getData();
        if ($produit instanceof Produit && $produit->hasImage()) {
            $builder->add('supprimerImage', CheckboxType::class, ['label' => 'Supprimer la photo actuelle', 'mapped' => false, 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Produit::class]);
    }
}
