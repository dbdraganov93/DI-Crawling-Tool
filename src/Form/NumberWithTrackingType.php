<?php

// src/Form/NumberWithTrackingType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class NumberWithTrackingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('number', TextType::class, [
                'label' => 'Brochure Number',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('tracking_pixel', TextType::class, [
                'label' => 'Tracking Pixel',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('validity_start', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('validity_end', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('visibility_start', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ]);
    }
}
