<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Création d'un compte client. Le mot de passe en clair n'est pas lié à l'entité : le contrôleur le hache. */
class InscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom et prénom',
                'attr' => ['autocomplete' => 'name'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez votre nom.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez votre email.'), new Assert\Email(message: 'Cet email n\'est pas valide.')],
            ])
            ->add('motDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
                'first_options' => ['label' => 'Mot de passe', 'help' => '10 caractères minimum', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisissez un mot de passe.'),
                    new Assert\Length(min: 10, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
                    new Assert\NotCompromisedPassword(message: 'Ce mot de passe apparaît dans des fuites de données connues : choisissez-en un autre.', skipOnError: true),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Utilisateur::class]);
    }
}
