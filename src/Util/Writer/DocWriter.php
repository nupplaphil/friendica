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

namespace Friendica\Util\Writer;

use Friendica\Core\Renderer;
use Friendica\Database\Definition\DbaDefinition;
use Friendica\Network\HTTPException\ServiceUnavailableException;

/**
 * Utility class to write content into the '/doc' directory
 */
class DocWriter
{
	/** @var string the relativ path to the database specification */
	const DOC_PATH_PREFIX = '/spec/database/';

	/**
	 * Creates all database definitions as Markdown fields and create the mkdoc config file.
	 *
	 * @param DbaDefinition $definition The Database definition class
	 * @param string        $basePath   The basepath of Friendica
	 *
	 * @return void
	 * @throws ServiceUnavailableException in really unexpected cases!
	 */
	public static function writeDbDefinition(DbaDefinition $definition, string $basePath)
	{
		if (!empty($branch) && substr($branch, -1) !== '/') {
			$branch .= '/';
		}

		$table_header = [
			[
				'name'    => 'Table',
				'comment' => 'Comment',
			],
			[
				'name'    => '-',
				'comment' => '-',
			]
		];

		$tables = [];

		$tables_length = [
			'name'    => 5,
			'comment' => 7
		];

		$table_names = [];

		foreach ($definition->getAll() as $name => $definition) {
			$table_names[] = $name;

			$indexes = [
				[
					'name'   => 'Name',
					'fields' => 'Fields',
				],
				[
					'name'   => '-',
					'fields' => '-',
				]
			];

			$lengths = [
				'name'   => 6,
				'fields' => 8
			];
			foreach ($definition['indexes'] as $key => $value) {
				$fieldlist = implode(', ', $value);
				$index     = [
					'name'   => $key,
					'fields' => $fieldlist,
				];

				foreach ($index as $fieldName => $fieldvalue) {
					$lengths[$fieldName] = max($lengths[$fieldName] ?? 0, strlen($fieldvalue));
				}
				$indexes[] = $index;
			}

			array_walk_recursive($indexes, function (&$value, $key) use ($lengths) {
				$value = str_pad($value, $lengths[$key], $value === '-' ? '-' : ' ');
			});

			$foreign = [
				[
					'field'       => 'Field',
					'targettable' => 'Target Table',
					'targetfield' => 'Target Field',
				],
				[
					'field'       => '-',
					'targettable' => '-',
					'targetfield' => '-',
				]
			];
			$lengths_foreign = [
				'field'       => 5,
				'targettable' => 12,
				'targetfield' => 12,
			];
			$has_foreign = false;

			$fields = [
				[
					'name'    => 'Field',
					'comment' => 'Description',
					'type'    => 'Type',
					'null'    => 'Null',
					'primary' => 'Key',
					'default' => 'Default',
					'extra'   => 'Extra',
				],
				[
					'name'    => '-',
					'comment' => '-',
					'type'    => '-',
					'null'    => '-',
					'primary' => '-',
					'default' => '-',
					'extra'   => '-',
				]
			];
			$lengths = [
				'name'    => 5,
				'comment' => 11,
				'type'    => 4,
				'null'    => 4,
				'primary' => 3,
				'default' => 7,
				'extra'   => 5,
			];

			foreach ($definition['fields'] as $key => $value) {
				$field = [
					'name'    => $key,
					'comment' => $value['comment'] ?? '',
					'type'    => $value['type'],
					'null'    => ($value['not null'] ?? false) ? 'NO' : 'YES',
					'primary' => ($value['primary'] ?? false) ? 'PRI' : '',
					'default' => $value['default'] ?? 'NULL',
					'extra'   => $value['extra'] ?? '',
				];

				foreach ($field as $fieldName => $fieldvalue) {
					$lengths[$fieldName] = max($lengths[$fieldName] ?? 0, strlen($fieldvalue));
				}
				$fields[] = $field;

				if (!empty($value['foreign'])) {
					$has_foreign = true;

					$foreign_table = array_keys($value['foreign'])[0];
					$foreign_entry = [
						'field'       => $key,
						'targettable' => sprintf("[%s](./db_%s.md)", $foreign_table, $foreign_table),
						'targetfield' => array_values($value['foreign'])[0],
					];

					foreach ($foreign_entry as $fieldName => $fieldvalue) {
						$lengths_foreign[$fieldName] = max($lengths_foreign[$fieldName] ?? 0, strlen($fieldvalue));
					}

					$foreign[] = $foreign_entry;
				}
			}

			array_walk_recursive($fields, function (&$value, $key) use ($lengths) {
				$value = str_pad($value, $lengths[$key], $value === '-' ? '-' : ' ');
			});

			array_walk_recursive($foreign, function (&$value, $key) use ($lengths_foreign) {
				$value = str_pad($value, $lengths_foreign[$key], $value === '-' ? '-' : ' ');
			});

			$table = [
				'name'    => sprintf("[%s](./db_%s.md)", $name, $name),
				'comment' => $definition['comment'],
			];

			foreach ($table as $fieldName => $fieldvalue) {
				$tables_length[$fieldName] = max($tables_length[$fieldName] ?? 0, strlen($fieldvalue));
			}

			$tables[] = $table;

			$content = Renderer::replaceMacros(Renderer::getMarkupTemplate('doc/structure.tpl'), [
				'$name'        => $name,
				'$comment'     => $definition['comment'],
				'$fields'      => $fields,
				'$indexes'     => $indexes,
				'$has_foreign' => $has_foreign,
				'$foreign'     => $foreign,
			]);
			$filename = $basePath . '/doc' . static::DOC_PATH_PREFIX . '/db_' . $name . '.md';
			file_put_contents($filename, $content);
		}
		asort($tables);

		$tables = array_merge($table_header, $tables);

		array_walk_recursive($tables, function (&$value, $key) use ($tables_length) {
			$value = str_pad($value, $tables_length[$key], $value === '-' ? '-' : ' ');
		});

		$content = Renderer::replaceMacros(Renderer::getMarkupTemplate('doc/tables.tpl'), [
			'$tables' => $tables,
		]);
		$filename = $basePath . '/doc' . static::DOC_PATH_PREFIX . '/index.md';
		file_put_contents($filename, $content);

		asort($table_names);

		$content = Renderer::replaceMacros(Renderer::getMarkupTemplate('doc/mkdocs.yml.tpl'), [
			'$tables' => $table_names,
		]);
		$filename = $basePath . '/mkdocs.yml';
		file_put_contents($filename, $content);
	}
}
