<?php
namespace Sitegeist\Monocle\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Sitegeist\Monocle\Fusion\FusionView;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class RenderService {

    /**
     * @Flow\Inject
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @param ControllerContext $controllerContext
     * @param string $prototypeName
     * @param string $packageKey
     * @param array $props
     * @param string $propSet
     * @param array $locales
     * @return string
     * @throws \Neos\Flow\Mvc\Exception
     */
    public function renderPrototype(ControllerContext $controllerContext, string $prototypeName, string $packageKey, array $props, string $propSet = null, array $locales = []) : string
    {

        $fusionView = new FusionView();
        $fusionView->setControllerContext($controllerContext);
        $fusionView->setPackageKey($packageKey);

        $fusionRootPath = $this->configurationService->getSiteConfiguration($packageKey, ['cli', 'fusionRootPath']);

        $fusionView->setPackageKey($packageKey);
        $fusionView->setFusionPath($fusionRootPath);

        $fusionView->assignMultiple([
            'sitePackageKey' => $packageKey,
            'prototypeName' => $prototypeName,
            'propSet' => $propSet,
            'props' => $props,
            'locales' => $locales
        ]);


        return $fusionView->render();
    }

    public function exportRendering(string $rendering, string $path, string $filename) {
        Files::createDirectoryRecursively($path);
        file_put_contents($path . $filename .'.html', $rendering);
    }

}