<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Company;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ShopfullyForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'choice_label' => function (Company $company) {
                    return $company->getName() . ' (ID: ' . $company->getIprotoId() . ')';
                },
                'placeholder' => 'Select a company',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('locale', TextType::class, [
                'required' => true,
            ])
            ->add('numbers', CollectionType::class, [
                'entry_type' => NumberWithTrackingType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
            ]);
    }
}
