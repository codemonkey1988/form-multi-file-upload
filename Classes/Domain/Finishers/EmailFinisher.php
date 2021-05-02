<?php

declare(strict_types = 1);

namespace Codemonkey1988\FormMultiFileUpload\Domain\Finishers;

use Codemonkey1988\FormMultiFileUpload\Domain\Model\FormElements\MultiFileUpload;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

class EmailFinisher extends \TYPO3\CMS\Form\Domain\Finishers\EmailFinisher
{
    /**
     * @param FormRuntime $formRuntime
     * @return FluidEmail
     * @throws \TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException
     */
    protected function initializeFluidEmail(FormRuntime $formRuntime): FluidEmail
    {
        $mail = parent::initializeFluidEmail($formRuntime);
        $attachUploads = $this->parseOption('attachUploads');
        $elements = $formRuntime->getFormDefinition()->getRenderablesRecursively();

        if ($attachUploads) {
            foreach ($elements as $element) {
                if (!$element instanceof MultiFileUpload) {
                    continue;
                }
                $files = $formRuntime[$element->getIdentifier()];
                if ($files instanceof ObjectStorage) {
                    foreach ($files as $file) {
                        if ($file instanceof FileReference) {
                            $file = $file->getOriginalResource();
                        }
                        if ($file instanceof FileInterface) {
                            $mail->attach($file->getContents(), $file->getName(), $file->getMimeType());
                        }
                    }
                }
            }
        }
        return $mail;
    }
}
