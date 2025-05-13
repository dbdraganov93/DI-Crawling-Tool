<?php
// src/Form/NumberWithTrackingType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
            ]);
    }
}
