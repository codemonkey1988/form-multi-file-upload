<?php

declare(strict_types = 1);

namespace Codemonkey1988\FormMultiFileUpload\Domain\Model\FormElements;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\StringableFormElementInterface;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;

class MultiFileUpload extends AbstractFormElement implements StringableFormElementInterface
{
    public function initializeFormElement()
    {
        $dataType = sprintf(
            '%s<%s>',
            ObjectStorage::class,
            PseudoFileReference::class
        );
        $this->setDataType($dataType);
        parent::initializeFormElement();
    }

    /**
     * @param ObjectStorage $value
     * @return string
     */
    public function valueToString($value): string
    {
        $fileNames = [];
        foreach ($value as $file) {
            if ($file instanceof FileReference) {
                $file = $file->getOriginalResource();
            }
            if ($file instanceof FileInterface) {
                $fileNames[] = $file->getName();
            }
        }

        return $this->formatFilenames($fileNames);
    }

    /**
     * @param array $fileNames
     * @return string
     */
    protected function formatFilenames(array $fileNames): string
    {
        return implode(', ', $fileNames);
    }
}
