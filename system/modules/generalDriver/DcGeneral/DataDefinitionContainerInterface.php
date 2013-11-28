<?php
/**
 * PHP version 5
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace DcGeneral;

use DcGeneral\DataDefinition\ContainerInterface;
use DcGeneral\Exception\DcGeneralInvalidArgumentException;

interface DataDefinitionContainerInterface
{
	/**
	 * Add or override a definition in the container.
	 *
	 * @param string             $name       Name of the definition
	 *
	 * @param ContainerInterface $definition The definition to store.
	 *
	 * @return DataDefinitionContainerInterface
	 */
	public function setDefinition($name, $definition);

	/**
	 * Check if a definition is contained in the container.
	 *
	 * @param string $name The name of the definition to retrieve.
	 *
	 * @return bool
	 */
	public function hasDefinition($name);

	/**
	 * Retrieve a definition from the container (if it exists).
	 *
	 * If the definition does not exist, an exception is thrown.
	 *
	 * @param string $name The name of the definition to retrieve.
	 *
	 * @return ContainerInterface
	 *
	 * @throws DcGeneralInvalidArgumentException
	 */
	public function getDefinition($name);
}