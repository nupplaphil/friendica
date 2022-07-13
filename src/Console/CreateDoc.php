<?php
/**
 * @copyright Copyright (C) 2010-2022, the Friendica project
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Console;

use Friendica\Database\Definition\DbaDefinition;
use Friendica\Util\BasePath;
use Friendica\Util\Writer\DocWriter;

/**
 * Description of CreateDoc
 */
class CreateDoc extends \Asika\SimpleConsole\Console
{
	protected $helpOptions = ['h', 'help', '?'];

	/** @var DbaDefinition */
	protected $dbaDefinition;
	/** @var string */
	protected $basePath;

	/** {@inheritDoc} */
	protected function getHelp(): string
	{
		$help = <<<HELP
console createdocumentation - Generate the whole Friendica configuration
Usage
	bin/console createdocumentation [-h|--help|-?] [-v]

Description
	Generate the whole documentation of Friendica based on the dbstructure 

Commands
    createMkDocs Executes the generation of the mkdocs.yml file
    
Options
    -h|--help|-? Show help information
    -v           Show more debug information.
HELP;
		return $help;
	}

	public function __construct(DbaDefinition $dbaDefinition, BasePath $basePath, $argv = null)
	{
		parent::__construct($argv);

		$this->dbaDefinition = $dbaDefinition;
		$this->basePath      = $basePath->getPath();
	}

	/** {@inheritDoc} */
	protected function doExecute(): int
	{
		if ($this->getOption('v')) {
			$this->out('Class: ' . __CLASS__);
			$this->out('Arguments: ' . var_export($this->args, true));
			$this->out('Options: ' . var_export($this->options, true));
		}

		if (count($this->args) == 0) {
			$this->out($this->getHelp());
			return 0;
		}

		switch ($this->getArgument(0)) {
			case "createMkDocs":
				DocWriter::writeDbDefinition($this->dbaDefinition, $this->basePath);
				$this->out('documentation created.');
				return 0;
			default:
				throw new \Asika\SimpleConsole\CommandArgsException('Invalid command.');
		}
	}
}
