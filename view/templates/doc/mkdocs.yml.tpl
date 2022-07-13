#### DON'T EDIT THIS FILE AT ROOT, use /view/templates/mkdocs.yaml.tpl Template ####
# Copyright (C) 2010-2022, the Friendica project

# GNU AGPL version 3 or any later version

# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.

# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.

# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

# Project information
site_name: Friendica documentation
site_url: https://friendi.ca
site_author: The Friendica project
site_description: >-
  Friendica is a decentralised communications platform that integrates social communication.
  Our platform links to independent social projects and corporate services.
docs_dir: doc

# Repository
repo_url: https://github.com/friendica/friendica
repo_name: friendica/friendica
edit_uri: edit/develop/doc/

use_directory_urls: true

# Copyright
copyright: Copyright &copy; 2010-2022, the Friendica project

# Configuration
theme:
  name: 'material'
  icon:
    repo: fontawesome/brands/github

  # Don't include MkDocs' JavaScript
  include_search_page: false
  search_index_only: true

  # Default values, taken from mkdocs_theme.yml
  language: en
  features:
    - content.code.annotate
    # - content.tabs.link
    - content.tooltips
    # - header.autohide
    # - navigation.expand
    - navigation.indexes
    # - navigation.instant
    # - navigation.prune
    - navigation.sections
    - navigation.tabs
    # - navigation.tabs.sticky
    - navigation.top
    - navigation.tracking
    - search.highlight
    - search.share
    - search.suggest
    - toc.follow
    # - toc.integrate

  logo: img/friendica.svg
  favicon: img/friendica-32.png
plugins:
  - search
  - tags
  - i18n:
      default_language: en
      docs_structure: folder
      languages:
        en:
          name: English
          build: true
        de:
          name: Deutsch
          build: true
          site_name: Friendica Dokumentation
      nav_translations:
        de:
          User: Benutzer
          Quick Start: Schnellstart
          'Bugs and Issues': 'Bugs und Probleme'
          Configuration: Konfiguration
          Third Party: Dritthersteller
          'First Steps': Erste Schritte
          'You and other users': Du mit anderen Nutzer
          'Further information': Weiterführende Informationen

extra:
  homepage: https://friendi.ca

markdown_extensions:
  - pymdownx.highlight:
      anchor_linenums: true
  - pymdownx.inlinehilite
  - pymdownx.snippets
  - pymdownx.superfences
  - toc:
      permalink: "#"

nav:
  - En:
    - index.md
    - User:
      - First Steps:
        - user/account-basics.md
        - Quick Start:
          - user/quick-start/guide.md
          - user/quick-start/network.md
          - user/quick-start/groups-and-pages.md
          - user/quick-start/making-new-friends.md
          - user/quick-start/finally.md
        - user/text-editor.md
        - user/bbcode.md
        - user/text-comment.md
        - user/accesskeys.md
        - user/events.md
        - user/keyboard-shortcuts.md
      - You and other users:
        - user/connectors.md
        - user/making-friends.md
        - user/groups-and-privacy.md
        - user/tags-and-mentions.md
        - user/forums.md
        - user/chats.md
      - Further information:
        - user/move-account.md
        - user/export-import-contacts.md
        - user/remove-account.md
        - user/two-factor-authentication.md
        - user/faq.md
    - Admin:
      - Installation:
        - admin/install.md
        - admin/update.md
        - admin/migrate.md
      - Configuration:
        - admin/settings.md
        - admin/config.md
        - admin/ssl.md
        - admin/improve-performance.md
        - admin/tools.md
      - Third Party:
        - admin/installing-connectors.md
        - admin/install-ejabberd.md
      - admin/faq.md
    - Developer:
        - developer/index.md
        - Set Up:
          - developer/github.md
          - developer/vagrant.md
        - Code structure:
          - developer/domain-driven-design.md
          - developer/addons.md
          - developer/themes.md
          - developer/smarty3-templates.md
          - developer/addon-storage-backend.md
        - How To:
          - developer/translations.md
          - developer/composer.md
          - developer/how-to-move-classes-to-src.md
          - developer/tests.md
          - developer/autoloader.md
        - Specification:
          - API:
            - spec/api/index.md
            - spec/api/entities.md
            - spec/api/friendica.md
            - spec/api/mastodon.md
            - spec/api/twitter.md
            - spec/api/gnu-social.md
          - Database:
            - spec/database/index.md
{{foreach $tables as $table}}
            - spec/database/db_{{$table nofilter}}.md
{{/foreach}}
          - Protocol:
            - spec/protocol/protocol.md
            - spec/protocol/message-flow.md
    - 'Bugs and Issues': bugs-and-issues.md
