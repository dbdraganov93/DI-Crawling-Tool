<?php
// src/Form/CrawlerType.php

namespace App\Form;

use App\Entity\Crawler;
use App\Entity\Company;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrawlerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('type')
            ->add('source')
            ->add('cron')
            ->add('behaviour')
            ->add('status')
            ->add('script')
            ->add('companyId', EntityType::class, [
                'class' => Company::class,
                'choice_label' => 'id', // You can also use 'name' or another field for display
                'placeholder' => 'Select a Company',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Crawler::class,
        ]);
    }
}
