<?php

class Crawler_Form_RetailerInfoForm extends Zend_Form
{

    public function __construct($amountRequiredFields = 3)
    {
        parent::__construct();
        $this->addAttribs(array('role' => 'form',
            'id' => 'companyChange'));
        $this->setName('retailerInfoForm');

        $storeUrl = new Zend_Form_Element_Text('storeUrl');
        $storeUrl->addErrorMessage('Bitte korrekten UNV-Link des Stores angeben.')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->setLabel('UNV-Link Store:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $validityLength = new Zend_Form_Element_Text('validityLength');
        $validityLength->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->setLabel('Gültigkeitszeitraum:')
                ->setDecorators(array(
                    array('ViewHelper'),
                    array('Errors'),
                    array('Label',
                        array('tag' => 'div'))
                ))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $actionSelect = new Zend_Form_Element_Select('actionSelect');
        $actionSelect->setMultiOptions(array(
                    'nope' => 'Aktion wählen',
                    'ignore' => 'Löschen',
                    'update' => 'Update'
                ))
                ->addErrorMessage('Bitte Aktionstyp angeben.')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $separator = new Zend_Form_Element_Hidden('separator');
        $separator->setAttribs(array(
            'style' => 'clear: both'
        ));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Aufgabe anlegen')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $abort = new Zend_Form_Element_Reset('abort');
        $abort->setLabel('Abbrechen')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-danger'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(array(
            $storeUrl,
            $actionSelect,
            $validityLength));

        for ($fieldNo = 0; $fieldNo < $amountRequiredFields; $fieldNo++) {
            $fieldSelect = new Zend_Form_Element_Select("fieldSelect_$fieldNo");
            $fieldSelect->setLabel('zu änderndes Feld:')
                    ->setMultiOptions(array(
                        'nope' => 'Feld wählen',
                        'section' => 'Abteilungen',
                        'barrier_free' => 'Barrierefreiheit',
                        'text' => 'Beschreibung',
                        'bonus_card' => 'Bonusprogramme',
                        'email' => 'E-Mail',
                        'toilet' => 'Kundentoilette',
                        'storeHours' => 'Öffnungszeiten',
                        'parking' => 'Parkplätze',
                        'service' => 'Services',
                        'streetNumber' => 'Straßennummer',
                        'fax' => 'Telefax',
                        'phone' => 'Telefon',
                        'title' => 'Titel',
                        'website' => 'Webseite',
                        'payment' => 'Zahlungsmöglichkeiten',
                        'subtitle' => 'Zusatz'
                    ))
                    ->setDecorators(array(
                        array('ViewHelper'),
                        array('Errors'),
                        array('Label',
                            array('tag' => 'div'))
                    ))
                    ->setAttribs(array(
                        'class' => 'form-control',
                        'style' => 'width: 22%; float: left; margin-right: 2%;'
            ));

            $fieldText = new Zend_Form_Element_Text("fieldText_$fieldNo");
            $fieldText->setAttribs(array(
                        'class' => 'form-control',
                        'style' => 'width: 55%; float: left; margin-right: 2%;'
                    ))
                    ->setDecorators(array(
                        array('ViewHelper')
            ));

            $emptyValueCheckbox = new Zend_Form_Element_Checkbox("ignoreValue_$fieldNo");
            $emptyValueCheckbox->setLabel('ignorieren?')
                    ->setAttribs(
                            array(
                                'style' => 'width: 5%; float: left;'
                    ))
                    ->setOptions(array(array('placement' => 'APPEND')))
                    ->setDecorators(array(
                        array('ViewHelper'),
                        array('Errors'),
                        array('Label',
                            array('placement' => 'append'))
            ));

            $this->addElements(array(
                $fieldSelect,
                $fieldText,
                $emptyValueCheckbox
            ));
        }

        $this->addElements(array(
            $separator,
            $submit,
            $abort
        ));
    }

    public function isValid($data)
    {
        $isValid = parent::isValid($data);
        $pattern = '#\/\d+?\/store\/\d+$#';
        if (!preg_match($pattern, $data['storeUrl'])) {
            $isValid = FALSE;
        }

        $pattern = '#nope#';
        if (preg_match($pattern, $data['actionSelect'])) {
            $isValid = FALSE;
        }

        if (preg_match('#update#', $data['actionSelect'])) {
            for ($i = 0; $i < count(preg_grep('#fieldSelect#', array_keys($data))); $i++) {
                if (!preg_match($pattern, $data['fieldSelect_' . $i]) && !strlen($data['fieldText_' . $i]) && (int) $data['ignoreValue_' . $i] == 0) {
                    $isValid = FALSE;
                }
            }
        }

        return $isValid;
    }

}
