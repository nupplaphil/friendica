# Upgrade

All notable changes to the **Friendica Core** will be documented in this file.
As an Addon maintainer or Friendica Developer you can inform yourself about all deprecations or breaking changes.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project [promises Backward Compatibility](index.md#backward-compatibility).

## [Version 2026.08](https://github.com/friendica/friendica/compare/2026.05...develop) - (unreleased)

### Changed

- **BREAKING**: The icon library has been changed from Font Awesome to Remix Icons. Theme developers must replace `fa-*` CSS classes with their `ri-*` equivalents. The Font Awesome dependency has been removed. Any theme or addon that relies on Font Awesome must either include it themselves or migrate to Remix Icons.

   *Before*
   ```html
   <i class="fa fa-search"></i>
   ```

   *After*
   ```html
   <i class="ri-search-line"></i>
   ```

### Removed

- Dropped support for PHP 7.4, 8.0 and 8.1.

### Deprecated

- Remove usage of `\Friendica\BaseRepository::$factory`, create a `getFactory()` instead.

   *Before*
   ```php
   use Friendica\BaseRepository;
   use Friendica\Database\Database;
   use Psr\Log\LoggerInterface;

   class CustomRepository extends BaseRepository
   {
       /** @var CustomFactory */
       protected $factory;

       public function __construct(Database $database, LoggerInterface $logger, CustomFactory $factory)
       {
           parent::__construct($database, $logger, $factory);
       }

       private function selectOne(array $condition, array $params = []): CustomEntity
       {
           $fields = $this->_selectFirstRowAsArray($condition, $params);

           return $this->factory->createFromTableRow($fields);
       }

       // …
   }
   ```

   *After*
   ```php
   use Friendica\BaseRepository;
   use Friendica\Database\Database;
   use Psr\Log\LoggerInterface;

   class CustomRepository extends BaseRepository
   {
       public function __construct(Database $database, LoggerInterface $logger, private CustomFactory $entityFactory)
       {
           parent::__construct($database, $logger, $entityFactory);
       }

       protected function getFactory(): CustomFactory
       {
           return $this->entityFactory;
       }

       private function selectOne(array $condition, array $params = []): CustomEntity
       {
           $fields = $this->_selectFirstRowAsArray($condition, $params);

           return $this->getFactory()->createFromTableRow($fields);
       }

       // …
   }
   ```

## [Version 2026.05](https://github.com/friendica/friendica/compare/2026.01...2026.05) - 2026-05-20

### Deprecated

- Support for PHP 7.4, 8.0 and 8.1 is deprecated and will be removed in the next release.

### Removed

- **BREAKING**: The themes `duepuntozero`, `smoothly` and `quattro` are removed. Switch to a supported theme like `frio` or `vier`.

## [Version 2026.01](https://github.com/friendica/friendica/compare/2024.12-1...2026.01) - 2026-01-27

### Added

- New composer script `bin/composer.phar run install:prod` to install all dependencies except the dev-libraries, but with autoloader optimization for production servers.
- New interface `Friendica\AppHelper` as replacement for `Friendica\App` added.

   *Before*
   ```php
   public function __construct(
       private \Friendica\App $app,
   ) {}
   ```

   *After*
   ```php
   public function __construct(
       private \Friendica\AppHelper $appHelper,
   ) {}
   ```
- New method `Friendica\DI::appHelper()` added to get the implementation of the `AppHelper` without constructor injection.

   *Before*
   ```php
   \Friendica\DI::app()->…();
   ```

   *After*
   ```php
   \Friendica\DI::appHelper()->…();
   ```
- New interface `Friendica\Core\Addon\AddonHelper` added as replacement for the `Friendica\Core\Addon` class.

   *Before*
   ```php
   \Friendica\Addon::isEnabled($addonId);
   ```

   *After* – via constructor injection
   ```php
   public function __construct(
       private \Friendica\Core\Addon\AddonHelper $addonHelper,
   ) {}

   $this->addonHelper->isAddonEnabled($addonId);
   ```
- New method `Friendica\DI::addonHelper()` added to get the implementation of the `Friendica\Core\Addon\AddonHelper` without constructor injection.

   *Before*
   ```php
   \Friendica\Addon::isEnabled($addonId);
   ```

   *After*
   ```php
   \Friendica\DI::addonHelper()->isAddonEnabled($addonId);
   ```
- New class `Friendica\Core\Addon\AddonInfo` added as an value object for the header information about an addon.
- New interface `Friendica\Core\Logger\Factory\LoggerFactory` added so addons can provide a custom `Psr\Log\LoggerInterface` implementation.

### Changed

- **BREAKING**: The class `Friendica\App` was completely refactored and marked as internal, work with `Friendica\AppHelper` instead.
- **BREAKING**: The `contact_block_end` hook provides a HTML string instead of an array.

   *Before*
   ```php
   function my_addon_contact_block_end(array &$data) {
       $data['output'] .= '<div>Extra content</div>';
   }
   ```

   *After*
   ```php
   function my_addon_contact_block_end(string &$html) {
       $html .= '<div>Extra content</div>';
   }
   ```
- The `bin/composer.phar install` command no longer optimizes the autoloader file to avoid various problems when adding/removing classes in dev. Run `bin/composer.phar install -o` if you want autoloader optimization.
- Downgrade shebang from `bin/bash` to `bin/sh` in `bin/console`.
- Uncaught exceptions are logged as CRITICAL instead as ERROR.

### Fixed

- The command `bin/console.php addon list enabled` shows a list of enabled addons instead of all addons, by @Art4 in [#14687](https://github.com/friendica/friendica/pull/14687).
- The command `bin/console.php addon list disabled` shows a list of disabled addons instead of an empty list, by @Art4 in [#14687](https://github.com/friendica/friendica/pull/14687).
- The `contact_block_end` hook has been fixed and can now change the content of the contact widget.

### Deprecated

- `bin/daemon.php` is deprecated in favor of `bin/console daemon` by @nupplaphil in [#14642](https://github.com/friendica/friendica/pull/14642)
- `bin/jetstream.php` is deprecated in favor of `bin/console jetstream` by @nupplaphil in [#14655](https://github.com/friendica/friendica/pull/14655)
- `bin/worker.php` is deprecated in favor of `bin/console worker` by @nupplaphil in [#14659](https://github.com/friendica/friendica/pull/14659)
- Providing strategies via `strategies.config.php` file in addons is deprecated and will stop working in 5 months, please use PHP hooks instead and remove the `strategies.config.php` file in your addon.
- Class `Friendica\Core\Addon` is deprecated and will be removed after 5 months, use implementation of `Friendica\Core\Addon\AddonHelper` instead.

   *Before*
   ```php
   \Friendica\Addon::isEnabled($addonId);
   ```

   *After* – via constructor injection
   ```php
   public function __construct(
       private \Friendica\Core\Addon\AddonHelper $addonHelper,
   ) {}

   $this->addonHelper->isAddonEnabled($addonId);
   ```

   *After* – via DI
   ```php
   \Friendica\DI::addonHelper()->isAddonEnabled($addonId);
   ```
- Class `Friendica\Core\Addon\Model\AddonLoader` is deprecated and will be removed after 5 months, use implementation of `Friendica\Core\Addon\AddonHelper` via constructor injection or `\Friendica\DI::addonHelper()` instead.
- Interface `Friendica\Core\Addon\Capability\ICanLoadAddons` is deprecated and will be removed after 5 months, use implementation of `\Friendica\Core\Addon\AddonHelper` via constructor injection or `\Friendica\DI::addonHelper()` instead.
- Class `Friendica\Core\Logger` is deprecated, use constructor injection or `Friendica\Di::logger()` instead.

   *Before*
   ```php
   \Friendica\Core\Logger::info('Message', ['key' => 'value']);
   ```

   *After* – via constructor injection
   ```php
   public function __construct(
       private \Psr\Log\LoggerInterface $logger,
   ) {}

   $this->logger->info('Message', ['key' => 'value']);
   ```

   *After* – via DI
   ```php
   \Friendica\DI::logger()->info('Message', ['key' => 'value']);
   ```
- Class `Friendica\Core\Logger\Factory\AbstractLoggerTypeFactory` is deprecated and will be removed after 5 months, implement `\Friendica\Core\Logger\Factory\LoggerFactory` instead.
- Class `Friendica\Core\Logger\Factory\Logger` is deprecated and will be removed after 5 months, implement `\Friendica\Core\Logger\Factory\LoggerFactory` instead.
- Class `Friendica\Core\Logger\Factory\StreamLogger` is deprecated and will be removed after 5 months, implement `\Friendica\Core\Logger\Factory\LoggerFactory` instead.
- Class `Friendica\Core\Logger\Factory\SyslogLogger` is deprecated and will be removed after 5 months, implement `\Friendica\Core\Logger\Factory\LoggerFactory` instead.
- The method `\Friendica\BaseRepository::_selectOne()` is deprecated, use `\Friendica\BaseRepository::_selectFirstRowAsArray()` instead.

   *Before*
   ```php
   return $this->_selectOne($condition, $params);
   ```

   *After*
   ```php
   $fields = $this->_selectFirstRowAsArray($condition, $params);

   return $this->factory->createFromTableRow($fields);
   ```

### Removed

- **BREAKING**: `Friendica\DI::app()` was removed, use `Friendica\DI::appHelper()` instead.

   *Before*
   ```php
   \Friendica\DI::app()->…();
   ```

   *After*
   ```php
   \Friendica\DI::appHelper()->…();
   ```
- **BREAKING**: `Friendica\Core\Logger::enableWorker()` and `Friendica\Core\Logger::disableWorker()` were removed.
