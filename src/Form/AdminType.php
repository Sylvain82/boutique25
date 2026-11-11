<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ajout d'un administrateur, ou changement de mot de passe (option « avec_email » à false).
 * Le mot de passe en clair n'est pas lié à l'entité : le contrôleur le hache.
 */
class AdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['avec_email']) {
            $builder->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ]);
        }
        $builder->add('motDePasse', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
            'first_options' => ['label' => 'Mot de passe', 'help' => '10 caractères minimum', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'constraints' => [
                new Assert\NotBlank(message: 'Choisissez un mot de passe.'),
                new Assert\Length(min: 10, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['avec_email' => true]);
        $resolver->setAllowedTypes('avec_email', 'bool');
    }
}
