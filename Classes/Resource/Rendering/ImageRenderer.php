<?php
namespace Schnitzler\FluidStyledResponsiveImages\Resource\Rendering;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder;

/**
 * Class ImageRenderer
 * @package Schnitzler\FluidStyledResponsiveImages\Resource\Rendering
 */
class ImageRenderer implements FileRendererInterface
{

    /**
     * @var TagBuilder
     */
    static protected $tagBuilder;

    /**
     * @var ImageRendererConfiguration
     */
    static protected $configuration;

    /**
     * @var array
     */
    protected $possibleMimeTypes = [
        'image/jpg',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    /**
     * @var array
     */
    protected $sizes = [];

    /**
     * @var array
     */
    protected $srcset = [];

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $defaultWidth;

    /**
     * @var string
     */
    protected $defaultHeight;

    /**
     * @return ImageRendererConfiguration
     */
    protected function getConfiguration()
    {
        if (!static::$configuration instanceof ImageRendererConfiguration) {
            static::$configuration = GeneralUtility::makeInstance(ImageRendererConfiguration::class);
        }

        return static::$configuration;
    }

    /**
     * @return TagBuilder
     */
    protected function getTagBuilder()
    {
        if (!static::$tagBuilder instanceof TagBuilder) {
            static::$tagBuilder = GeneralUtility::makeInstance(TagBuilder::class);
        }

        return static::$tagBuilder;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 5;
    }

    /**
     * @param FileInterface $file
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return TYPO3_MODE === 'FE' && in_array($file->getMimeType(), $this->possibleMimeTypes, true);
    }

    /**
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     * @return string
     */
    public function render(
        FileInterface $file,
        $width,
        $height,
        array $options = [],
        $usedPathsRelativeToCurrentScript = false
    ) {
        $this->reset();

        $this->defaultWidth = $width;
        $this->defaultHeight = $height;

        if (is_callable([$file, 'getOriginalFile'])) {
            /** @var FileReference $file */
            $originalFile = $file->getOriginalFile();
        } else {
            $originalFile = $file;
        }

        try {
            $defaultProcessConfiguration = [];
            $defaultProcessConfiguration['width'] = '360m';
            $defaultProcessConfiguration['crop'] = $file->getProperty('crop');
        } catch (\InvalidArgumentException $e) {
            $defaultProcessConfiguration['crop'] = '';
        }

        /** @var  \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj */
        $cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        $conf = array(
            'uidInList' => $file->getReferenceProperty('uid_foreign')
        );

        $mediaCObject = $cObj->getRecords($file->getReferenceProperty('tablenames'), $conf);

        if(count($mediaCObject) !== 1) {
            throw new \RuntimeException;
        }

        $colPosOfMediaObject = intval($mediaCObject[0]['colPos'],10);

        $this->processSourceCollection($originalFile,$colPosOfMediaObject, $defaultProcessConfiguration);

        $src = $originalFile->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            $defaultProcessConfiguration
        )->getPublicUrl();

        try {
            $alt = $file->getProperty('alternative');
        } catch (\InvalidArgumentException $e) {
            $alt = '';
        }

        try {
            $title = $file->getProperty('title');
        } catch (\InvalidArgumentException $e) {
            $title = '';
        }

        return $this->buildImageTag($src, $alt, $title);
    }

    /**
     * @return void
     */
    protected function reset()
    {
        $this->sizes = [];
        $this->srcset = [];
        $this->data = [];
    }

    /**
     * @param File $originalFile
     * @param int $colPos The colpos of the content element that is using the file
     * @param array $defaultProcessConfiguration
     */
    protected function processSourceCollection(File $originalFile, $colPos, array $defaultProcessConfiguration)
    {
        $configuration = $this->getConfiguration();

        $sourceCollection = $configuration->getSourceCollection($colPos);

        foreach ($sourceCollection as $sourceCollectionEntry) {
            try {
                if (!is_array($sourceCollectionEntry)) {
                    throw new \RuntimeException();
                }

                if (isset($sourceCollectionEntry['sizes'])) {
                    $this->sizes[] = trim($sourceCollectionEntry['sizes'], ' ,');
                }
                
                $localProcessingConfiguration = $defaultProcessConfiguration;

                $localProcessingConfiguration['width'] = $sourceCollectionEntry['width'];

                $processedFile = $originalFile->process(
                    ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                    $localProcessingConfiguration
                );

                $url = $configuration->getAbsRefPrefix() . $processedFile->getPublicUrl();

                $this->data['data-' . $sourceCollectionEntry['dataKey']] = $url;
                $this->srcset[] = $url . rtrim(' ' . $sourceCollectionEntry['srcset'] ?: '');
            } catch (\Exception $ignoredException) {
                continue;
            }
        }
    }

    /**
     * @param string $src
     * @param string $alt
     * @param string $title
     *
     * @return string
     */
    protected function buildImageTag($src, $alt = '', $title = '')
    {
        $tagBuilder = $this->getTagBuilder();
        $configuration = $this->getConfiguration();

        $tagBuilder->reset();
        $tagBuilder->setTagName('img');
        $tagBuilder->addAttribute('src', $src);
        $tagBuilder->addAttribute('alt', $alt);
        $tagBuilder->addAttribute('title', $title);

        switch ($configuration->getLayoutKey()) {
            case 'srcset':
                if (!empty($this->srcset)) {
                    $tagBuilder->addAttribute('srcset', implode(', ', $this->srcset));
                }

                $tagBuilder->addAttribute('sizes', implode(', ', $this->sizes));
                break;
            case 'data':
                if (!empty($this->data)) {
                    foreach ($this->data as $key => $value) {
                        $tagBuilder->addAttribute($key, $value);
                    }
                }
                break;
            default:
                $tagBuilder->addAttributes([
                    'width' => (int)$this->defaultWidth,
                    'height' => (int)$this->defaultHeight,
                ]);
                break;
        }

        return $tagBuilder->render();
    }

}
