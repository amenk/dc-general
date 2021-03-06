<?php
/**
 * PHP version 5
 *
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

/** @var Pimple $container */

/**
 * This function creates the default data definition container.
 * To override the type, set your own implementation override the value of
 * $container['dc-general.data-definition-container.factory'].
 *
 * @return \ContaoCommunityAlliance\DcGeneral\DataDefinitionContainerInterface $container
 */
$container['dc-general.data-definition-container.factory.default'] = $container->protect(
    function () {
        return new \ContaoCommunityAlliance\DcGeneral\DataDefinitionContainer();
    }
);

if (!isset($container['dc-general.data-definition-container.factory'])) {
    $container['dc-general.data-definition-container.factory'] =
        $container->raw('dc-general.data-definition-container.factory.default');
}

$container['dc-general.data-definition-container'] = $container->share(
    function ($container) {
        $factory = $container['dc-general.data-definition-container.factory'];

        /** @var \ContaoCommunityAlliance\DcGeneral\DataDefinitionContainerInterface $container */
        $container = $factory();

        return $container;
    }
);
