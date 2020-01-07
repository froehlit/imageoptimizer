<?php

namespace Lemming\Imageoptimizer;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileAspects
{

    /**
     * @var OptimizeImageService
     */
    protected $service;

    /**
     * @param OptimizeImageService $optimizeImageService
     */
    public function __construct(OptimizeImageService $optimizeImageService)
    {
        $this->service = $optimizeImageService;
    }

    /**
     * Called when a new file is uploaded
     *
     * @param string $targetFileName
     * @param Folder $targetFolder
     * @param string $sourceFilePath
     * @return string Modified target file name
     * @throws BinaryNotFoundException
     */
    public function addFile($targetFileName, Folder $targetFolder, $sourceFilePath)
    {
        $this->service->process($sourceFilePath, pathinfo($targetFileName)['extension'], true);
    }

    /**
     * Called when a file is overwritten
     *
     * @param FileInterface $file The file to replace
     * @param string $localFilePath The uploaded file
     * @throws BinaryNotFoundException
     */
    public function replaceFile(FileInterface $file, $localFilePath)
    {
        $this->service->process($localFilePath, $file->getExtension(), true);
    }

    /**
     * Called when a file was processed
     *
     * @param \TYPO3\CMS\Core\Resource\Service\FileProcessingService $fileProcessingService
     * @param \TYPO3\CMS\Core\Resource\Driver\DriverInterface $driver
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     * @throws BinaryNotFoundException
     */
    public function processFile($fileProcessingService, $driver, $processedFile)
    {
        if ($processedFile->isUpdated() === true) {
            $file = Environment::getPublicPath() . '/' . $processedFile->getPublicUrl();
            if ($processedFile->usesOriginalFile()) {
                $file = $processedFile->getForLocalProcessing();
            }

            $this->service->process($file, $processedFile->getExtension());
            $this->updateProcessedFile($processedFile, $file);
        }
    }

    /**
     * Update the processed file.
     *
     * @param \TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     */
    protected function updateProcessedFile(ProcessedFile $processedFile, $file)
    {
        /** @var GraphicalFunctions $graphicalFunctions */
        $graphicalFunctions = GeneralUtility::makeInstance(GraphicalFunctions::class);
        $imageDimensions = $graphicalFunctions->getImageDimensions($file);
        $properties = [
            'width' => $imageDimensions[0],
            'height' => $imageDimensions[1],
            'size' => filesize($file),
            'checksum' => $processedFile->getTask()->getConfigurationChecksum()
        ];
        $processedFile->updateProperties($properties);
        if ($processedFile->usesOriginalFile()) {
            $processedFile->setName($processedFile->getTask()->getTargetFileName());
            $processedFile->updateWithLocalFile($file);
            $processedFile->getTask()->setExecuted(true);
        }

        /** @var ProcessedFileRepository $processedFileRepository */
        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFileRepository->add($processedFile);
    }
}
