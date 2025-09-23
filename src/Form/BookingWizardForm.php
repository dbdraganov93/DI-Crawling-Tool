<?php

namespace App\Form;

use App\Service\IprotoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class BookingWizardForm extends AbstractType
{
    public function __construct(private IprotoService $iprotoService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('owner', ChoiceType::class, [
                'label' => 'Select Owner',
                'choices' => $this->normalizeOwners($this->iprotoService->getAllOwners()),
                'placeholder' => 'Choose an owner',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-error-message' => 'Please select an owner',
                ],
            ])
            ->add('company', ChoiceType::class, [
                'label' => 'Select Company',
                'choices' => [],
                'placeholder' => 'Select a company',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-error-message' => 'Please select a company',
                ],
            ])
            ->add('selectedBrochures', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'id' => 'booking-wizard-selected-brochures',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $event->getData();

            if (empty($data['owner'])) {
                return;
            }

            $companies = $this->iprotoService->getAllCompanies($data['owner']);

            $choices = [];
            foreach ($companies as $company) {
                if (!isset($company['id'], $company['title'])) {
                    continue;
                }
                $choices[$company['title'] . ' (ID: ' . $company['id'] . ')'] = $company['id'];
            }

            $form->add('company', ChoiceType::class, [
                'label' => 'Select Company',
                'choices' => $choices,
                'placeholder' => 'Select a company',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-error-message' => 'Please select a company',
                ],
            ]);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $owners
     *
     * @return array<string, int|string>
     */
    private function normalizeOwners(array $owners): array
    {
        $normalized = [];
        foreach ($owners as $owner) {
            if (!isset($owner['id'], $owner['title'])) {
                continue;
            }
            $normalized[$owner['title'] . ' (ID: ' . $owner['id'] . ')'] = $owner['id'];
        }

        return $normalized;
    }
}
