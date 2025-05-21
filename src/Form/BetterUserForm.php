<?php

namespace DNADesign\UserFormExtras\Form;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\UserForms\Form\UserForm;

/**
* This class extension is to allow to resurface the error message
* after a php validation
* in the same way as the front end validation
* if the "DisplayErrorMessagesAtTop" option is selected
*
* As there are no hooks on USerDefinedForm and UserForm
* This class is being injected to replace UserForm
*/

class BetterUserForm extends UserForm
{
    public function validate(): ValidationResult
    {
        if ($this->validator) {
            $errors = $this->validator->validate();

            if ($errors) {
                // Load errors into session and post back
                $data = $this->getData();

                // Encode validation messages as XML before saving into session state
                // As per Form::addErrorMessage()
                $errors = array_map(function ($error) {
                    // Encode message as XML by default
                    if ($error['message'] instanceof DBField) {
                        $error['message'] = $error['message']->forTemplate();
                        ;
                    } else {
                        $error['message'] = Convert::raw2xml($error['message']);
                    }
                    return $error;
                }, $errors);

                $request = Injector::inst()->get(HTTPRequest::class);
                $session = $request->getSession();

                $session->set("FormInfo.{$this->FormName()}.errors", $errors);
                $session->set("FormInfo.{$this->FormName()}.data", $data);

                // If option is to display error messages at the top
                // Set the Form session message as well
                $controller = $this->getController();
                if ($controller && $controller->data()->DisplayErrorMessagesAtTop) {
                    $errorList = new ArrayList();
                    
                    foreach ($errors as $error) {
                        $errorList->push(array(
                            'Target' => '#'.$error['fieldName'],
                            'Message' => $error['message']
                        ));
                    }

                    $errorHTML = $controller
                        ->customise(new ArrayData(array('ErrorList' => $errorList)))
                        ->renderWith('UserFormPhpErrors');

                    $this->sessionMessage($errorHTML, 'bad', false);
                }

                return false;
            }
        }

        return true;
    }
}
