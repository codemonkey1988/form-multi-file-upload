<?php

declare(strict_types = 1);

namespace Codemonkey1988\FormMultiFileUpload\Hooks;

use Codemonkey1988\FormMultiFileUpload\Domain\Model\FormElements\MultiFileUpload;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Validation\Exception\NoSuchValidatorException;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter;
use TYPO3\CMS\Form\Mvc\Validation\CountValidator;
use TYPO3\CMS\Form\Mvc\Validation\MimeTypeValidator;

class FormRuntimeHooks
{
    /**
     * @param FormRuntime $formRuntime
     * @param RenderableInterface $element
     * @param mixed $value
     * @param array $requestArguments
     * @return mixed
     */
    public function afterSubmit(
        FormRuntime $formRuntime,
        RenderableInterface $element,
        $value,
        array $requestArguments
    ) {
        if (is_array($value) && $element instanceof MultiFileUpload) {
            $mappedValue = [];
            $propertyMappingConfiguration = $formRuntime
                ->getFormDefinition()
                ->getProcessingRule($element->getIdentifier())
                ->getPropertyMappingConfiguration();

            foreach ($value as $singleFileUpload) {
                $uniquePropertyName = uniqid();
                $propertyMappingConfiguration->allowProperties($uniquePropertyName);
                $mappedValue[$uniquePropertyName] = $singleFileUpload;
            }

            $this->addMultiUploadValidators($element, array_keys($mappedValue));

            return $mappedValue;
        }

        return $value;
    }

    /**
     * @param MultiFileUpload $element
     * @param array $propertyNames
     * @throws NoSuchValidatorException
     * @throws FormDefinitionConsistencyException
     */
    protected function addMultiUploadValidators(MultiFileUpload $element, array $propertyNames)
    {
        /** @var \TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration $propertyMappingConfiguration */
        $propertyMappingConfiguration = $element->getRootForm()->getProcessingRule($element->getIdentifier())->getPropertyMappingConfiguration();

        $allowedMimeTypes = [];
        $validators = [];
        if (isset($element->getProperties()['allowedMimeTypes']) && \is_array($element->getProperties()['allowedMimeTypes'])) {
            $allowedMimeTypes = array_filter($element->getProperties()['allowedMimeTypes']);
        }
        if (!empty($allowedMimeTypes)) {
            $mimeTypeValidator = GeneralUtility::makeInstance(ObjectManager::class)
                ->get(MimeTypeValidator::class, ['allowedMimeTypes' => $allowedMimeTypes]);
            $validators = [$mimeTypeValidator];
        }

        $processingRule = $element->getRootForm()->getProcessingRule($element->getIdentifier());
        foreach ($processingRule->getValidators() as $validator) {
            if (!($validator instanceof NotEmptyValidator) && !($validator instanceof CountValidator)) {
                $validators[] = $validator;
                $processingRule->removeValidator($validator);
            }
        }

        $uploadConfiguration = [
            UploadedFileReferenceConverter::CONFIGURATION_FILE_VALIDATORS => $validators,
            UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_CONFLICT_MODE => 'rename',
        ];

        $saveToFileMountIdentifier = $element->getProperties()['saveToFileMount'] ?? '';
        if ($this->checkSaveFileMountAccess($saveToFileMountIdentifier)) {
            $uploadConfiguration[UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER] = $saveToFileMountIdentifier;
        } else {
            $persistenceIdentifier = $element->getRootForm()->getPersistenceIdentifier();
            if (!empty($persistenceIdentifier)) {
                $pathinfo = PathUtility::pathinfo($persistenceIdentifier);
                $saveToFileMountIdentifier = $pathinfo['dirname'];
                if ($this->checkSaveFileMountAccess($saveToFileMountIdentifier)) {
                    $uploadConfiguration[UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER] = $saveToFileMountIdentifier;
                }
            }
        }

        foreach ($propertyNames as $propertyName) {
            $propertyMappingConfiguration
                ->forProperty($propertyName)
                ->setTypeConverter(GeneralUtility::makeInstance(ObjectManager::class)->get(UploadedFileReferenceConverter::class))
                ->setTypeConverterOptions(UploadedFileReferenceConverter::class, $uploadConfiguration);
        }
    }

    /**
     * @param string $saveToFileMountIdentifier
     * @return bool
     */
    protected function checkSaveFileMountAccess(string $saveToFileMountIdentifier): bool
    {
        if (empty($saveToFileMountIdentifier)) {
            return false;
        }

        $resourceFactory = GeneralUtility::makeInstance(ObjectManager::class)
            ->get(ResourceFactory::class);

        try {
            $resourceFactory->getFolderObjectFromCombinedIdentifier($saveToFileMountIdentifier);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
