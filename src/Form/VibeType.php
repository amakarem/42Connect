<?php

namespace App\Form;

use App\Entity\Vibe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VibeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('originalVibe', TextareaType::class, [
                'label' => 'Your original vibe',
            ])
            ->add('vibe', TextareaType::class, [
                'label' => 'Your vibe',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vibe::class,
        ]);
    }
}
