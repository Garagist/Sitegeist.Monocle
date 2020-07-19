<?php
namespace Sitegeist\Monocle\Command;

/**
 * This file is part of the Sitegeist.Monocle package
 *
 * (c) 2016
 * Martin Ficzel <ficzel@sitegeist.de>
 * Wilhelm Behncke <behncke@sitegeist.de>
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\Exception;
use Neos\Flow\Package\PackageInterface;
use Sitegeist\Monocle\Fusion\FusionService;
use Sitegeist\Monocle\Fusion\FusionView;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Sitegeist\Monocle\Service\RenderService;
use Symfony\Component\Yaml\Yaml;
use Sitegeist\Monocle\Service\DummyControllerContextTrait;
use Sitegeist\Monocle\Service\PackageKeyTrait;
use Sitegeist\Monocle\Service\ConfigurationService;

/**
 * Class StyleguideCommandController
 * @package Sitegeist\Monocle\Command
 */
class StyleguideCommandController extends CommandController
{
    use DummyControllerContextTrait, PackageKeyTrait;

    /**
     * @Flow\Inject
     * @var FusionService
     */
    protected $fusionService;

    /**
     * @Flow\Inject
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @Flow\Inject
     * @var RenderService
     */
    protected $renderService;

    /**
     * Get a list of all configured default styleguide viewports
     *
     * @param string $format Result encoding ``yaml`` and ``json`` are supported
     * @param string $packageKey site-package (defaults to first found)
     */
    public function viewportsCommand($format = 'json', $packageKey = null)
    {
        $sitePackageKey = $packageKey ?: $this->getDefaultSitePackageKey();
        $viewportPresets = $this->configurationService->getSiteConfiguration($sitePackageKey, 'ui.viewportPresets');
        $this->outputData($viewportPresets, $format);
    }

    /**
     * Get all styleguide items currently available
     *
     * @param string $format Result encoding ``yaml`` and ``json`` are supported
     * @param string $packageKey site-package (defaults to first found)
     */
    public function itemsCommand($format = 'json', $packageKey = null)
    {
        $sitePackageKey = $packageKey ?: $this->getDefaultSitePackageKey();

        $fusionAst = $this->fusionService->getMergedFusionObjectTreeForSitePackage($sitePackageKey);
        $styleguideObjects = $this->fusionService->getStyleguideObjectsFromFusionAst($fusionAst);

        $this->outputData($styleguideObjects, $format);
    }

    /**
     * Render a given fusion component to HTML
     *
     * @param string $prototypeName The prototype name of the component
     * @param string $packageKey site-package (defaults to first found)
     * @param string $propSet The propSet used for the preview
     * @param string $props Custom props for the preview
     * @param string $locales Custom locales for the preview
     * @return void
     */
    public function renderCommand($prototypeName, $packageKey = null, $propSet = '__default', $props = '', $locales = '')
    {
        $sitePackageKey = $packageKey ?: $this->getDefaultSitePackageKey();
        $convertedProps = json_decode($props, true) ?? [];
        $convertedLocales = json_decode($locales, true) ?? [];

        $controllerContext = $this->createDummyControllerContext();

        $fusionView = new FusionView();
        $fusionView->setControllerContext($controllerContext);
        $fusionView->setPackageKey($sitePackageKey);

        $fusionRootPath = $this->configurationService->getSiteConfiguration($sitePackageKey, ['cli', 'fusionRootPath']);

        $fusionView->setPackageKey($sitePackageKey);
        $fusionView->setFusionPath($fusionRootPath);

        $fusionView->assignMultiple([
            'sitePackageKey' => $packageKey,
            'prototypeName' => $prototypeName,
            'propSet' => $propSet,
            'props' => $convertedProps,
            'locales' => $convertedLocales
        ]);

        $this->output($fusionView->render());
    }

    protected function outputData($data, $format)
    {
        switch ($format) {
            case 'json':
                $json = json_encode($data);
                $this->outputLine($json . chr(10));
                break;
            case 'yaml':
                $yaml = Yaml::dump($data, 99);
                $this->outputLine($yaml . chr(10));
                break;
            default:
                throw new \Exception(sprintf('Unsupported format %s', $format));
                break;
        }
    }

    /**
     * export all rendered prototypes into a directory
     *
     * @param string $packageKey
     * @param string $locales
     * @throws \Neos\Flow\Mvc\Exception
     * @throws \Neos\Neos\Domain\Exception
     */
    public function exportCommand(string $packageKey, $locales = '') {

        /** @var PackageInterface $package */
        $package = $this->packageManager->getPackage($packageKey);
        $exportPath = $package->getPackagePath() . 'export/';

        $fusionObjectTree = $this->fusionService->getMergedFusionObjectTreeForSitePackage($packageKey);
        $styleguideObjects = $this->fusionService->getStyleguideObjectsFromFusionAst($fusionObjectTree);
        $convertedLocales = json_decode($locales, true) ?? [];

        foreach ($styleguideObjects as $prototypeName => $styleguideObject) {
                $fusionAst =  $fusionObjectTree['__prototypes'][$prototypeName];
                $controllerContext = $this->createDummyControllerContext();
                $props = $fusionAst['__meta']['styleguide']['props'] ?? [];

                try {
                    $renderDefault = $this->renderService->renderPrototype($controllerContext, $prototypeName, $packageKey, [], null, $convertedLocales );
                    $this->renderService->exportRendering($renderDefault, $exportPath, $styleguideObject['path'] . '_default');
                } catch (Exception $e) {
                    \Neos\Flow\var_dump($props);
                    \Neos\Flow\var_dump($prototypeName);
                    \Neos\Flow\var_dump($e->getMessage());
                }


                if (isset($fusionAst['__meta']['styleguide']['propSets'])) {
                    foreach ($fusionAst['__meta']['styleguide']['propSets'] as $propSetName => $propSetValues) {
                        try {
                            $renderPropSet = $this->renderService->renderPrototype($controllerContext, $prototypeName, $packageKey, [], $propSetName, $convertedLocales );
                            $this->renderService->exportRendering($renderPropSet, $exportPath, $styleguideObject['path'] . '_' . $propSetName);
                        } catch (Exception $e) {
                            \Neos\Flow\var_dump($props);
                            \Neos\Flow\var_dump($prototypeName);
                            \Neos\Flow\var_dump($e->getMessage());
                        }
                    }
                }
            }
    }
}
