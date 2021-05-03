<?php

declare(strict_types = 1);

namespace Codemonkey1988\FormMultiFileUpload\ViewHelpers\Form;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Fluid\ViewHelpers\Form\UploadViewHelper;

class UploadedResourceViewHelper extends UploadViewHelper
{
    /**
     * @var HashService
     */
    protected $hashService;

    /**
     * @var \TYPO3\CMS\Extbase\Property\PropertyMapper
     */
    protected $propertyMapper;

    /**
     * @param HashService $hashService
     * @internal
     */
    public function injectHashService(HashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Property\PropertyMapper $propertyMapper
     * @internal
     */
    public function injectPropertyMapper(PropertyMapper $propertyMapper)
    {
        $this->propertyMapper = $propertyMapper;
    }

    /**
     * Initialize the arguments.
     *
     * @internal
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('as', 'string', '');
        $this->registerArgument('accept', 'array', 'Values for the accept attribute', false, []);
    }

    /**
     * @return string
     */
    public function render()
    {
        $output = '';

        $as = $this->arguments['as'];
        $accept = $this->arguments['accept'];
        $resources = $this->getUploadedResources();

        if ($this->getMappingResultsForProperty()->hasErrors()) {
            $this->removeUploadedResources($resources);
            $resources = null;
        }

        if (!empty($accept)) {
            $this->tag->addAttribute('accept', implode(',', $accept));
        }

        if ($resources !== null) {
            foreach ($resources as $key => $resource) {
                if ($resource instanceof FileReference) {
                    $resourcePointerValue = $resource->getUid();
                    $resourcePointerIdAttribute = '';
                    if ($this->hasArgument('id')) {
                        $resourcePointerIdAttribute = ' id="' . htmlspecialchars($this->arguments['id']) . '-file-reference' . $resourcePointerValue . '"';
                    }
                    if ($resourcePointerValue === null) {
                        // Newly created file reference which is not persisted yet.
                        // Use the file UID instead, but prefix it with "file:" to communicate this to the type converter
                        $resourcePointerValue = 'file:' . $resource->getOriginalResource()->getOriginalFile()->getUid();
                    }
                    $output .= sprintf(
                        '<input type="hidden" name="%s" value="%s" %s />',
                        htmlspecialchars($this->getName()) . '[' . $key . ']' . '[submittedFile][resourcePointer]',
                        htmlspecialchars($this->hashService->appendHmac((string)$resourcePointerValue)),
                        $resourcePointerIdAttribute
                    );

                    $this->templateVariableContainer->add($as, $resource);
                    $output .= $this->renderChildren();
                    $this->templateVariableContainer->remove($as);
                }
            }
        }

        $output .= parent::render();
        return $output;
    }

    /**
     * @return ObjectStorage|null
     */
    protected function getUploadedResources(): ?ObjectStorage
    {
        $resources = $this->getValueAttribute();
        if ($resources instanceof ObjectStorage) {
            return $resources;
        }
        return $this->propertyMapper->convert($resources, ObjectStorage::class);
    }

    protected function removeUploadedResources(ObjectStorage $resources): void
    {
        foreach ($resources as $resource) {
            if ($resource instanceof FileReference) {
                $resource = $resource->getOriginalResource();
            }
            $folder = $resource->getParentFolder();
            $resource->getOriginalFile()->getStorage()->deleteFile($resource->getOriginalFile());
            if ($folder->getFileCount() === 0 && $folder->getStorage()->countFoldersInFolder($folder) === 0) {
                $folder->delete();
            }
        }
    }
}
