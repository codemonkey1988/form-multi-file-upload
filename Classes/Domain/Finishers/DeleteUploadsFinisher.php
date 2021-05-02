<?php

declare(strict_types = 1);

namespace Codemonkey1988\FormMultiFileUpload\Domain\Finishers;

use Codemonkey1988\FormMultiFileUpload\Domain\Model\FormElements\MultiFileUpload;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Model\FormElements\FileUpload;

class DeleteUploadsFinisher extends AbstractFinisher
{
    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     */
    protected function executeInternal()
    {
        $formRuntime = $this->finisherContext->getFormRuntime();

        $uploadFolders = [];
        $elements = $formRuntime->getFormDefinition()->getRenderablesRecursively();
        foreach ($elements as $element) {
            if ($element instanceof FileUpload) {
                $file = $formRuntime[$element->getIdentifier()];
                if ($file) {
                    if ($file instanceof FileReference) {
                        $file = $file->getOriginalResource();
                    }
                    $folder = $file->getParentFolder();
                    $uploadFolders[$folder->getCombinedIdentifier()] = $folder;
                    $file->getStorage()->deleteFile($file->getOriginalFile());
                }
                continue;
            }
            if ($element instanceof MultiFileUpload) {
                $files = $formRuntime[$element->getIdentifier()];
                if ($files instanceof ObjectStorage) {
                    foreach ($files as $file) {
                        if ($file instanceof FileReference) {
                            $file = $file->getOriginalResource();
                        }
                        if ($file instanceof FileInterface) {
                            $folder = $file->getParentFolder();
                            $uploadFolders[$folder->getCombinedIdentifier()] = $folder;
                            $file->getStorage()->deleteFile($file->getOriginalFile());
                        }
                    }
                }
            }
        }

        $this->deleteEmptyUploadFolders($uploadFolders);
    }

    /**
     * note:
     * TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter::importUploadedResource()
     * creates a sub-folder for file uploads (e.g. .../form_<40-chars-hash>/actual.file)
     * @param Folder[] $folders
     */
    protected function deleteEmptyUploadFolders(array $folders): void
    {
        foreach ($folders as $folder) {
            if ($this->isEmptyFolder($folder)) {
                $folder->delete();
            }
        }
    }

    /**
     * @param Folder $folder
     * @return bool
     */
    protected function isEmptyFolder(Folder $folder): bool
    {
        return $folder->getFileCount() === 0
            && $folder->getStorage()->countFoldersInFolder($folder) === 0;
    }
}
