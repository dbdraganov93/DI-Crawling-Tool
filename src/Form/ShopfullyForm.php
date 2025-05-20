<?php
namespace App\Form;

use App\Service\IprotoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ShopfullyForm extends AbstractType
{
    private IprotoService $iprotoService;
    public function __construct(IprotoService $iprotoService)
    {
        $this->iprotoService = $iprotoService;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $owners = $this->normalizeOwners($this->iprotoService->getAllOwners());

        $builder
            ->add('owner', ChoiceType::class, [
                'label' => 'Select Owner',
                'choices' => $owners,
                'placeholder' => 'Choose an owner',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('company', ChoiceType::class, [
                'label' => 'Select Company',
                'choices' => [],
                'placeholder' => 'Select a company',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('locale', TextType::class, [
                'required' => true,
            ])
            ->add('prefix', TextType::class, [
                'required' => false,
                'label' => 'Prefix',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('suffix', TextType::class, [
                'required' => false,
                'label' => 'Suffix',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('numbers', CollectionType::class, [
                'entry_type' => NumberWithTrackingType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            if (!isset($data['owner'])) {
                return;
            }

            $companies = $this->iprotoService->getAllCompanies($data['owner']);
            $choices = [];

            foreach ($companies as $company) {
                $choices[$company['title'] . ' (ID: ' . $company['id'] . ')'] = $company['id'];
            }

            $form->add('company', ChoiceType::class, [
                'label' => 'Select Company',
                'choices' => $choices,
                'placeholder' => 'Select a company',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ]);
        });
    }


    private function normalizeOwners(array $owners): array
    {
        foreach ($owners as $owner) {
            $normalizedOwners[$owner['title'] . ' (ID: ' . $owner['id'] . ')'] = $owner['id'];
        }

        return $normalizedOwners;
    }
}
