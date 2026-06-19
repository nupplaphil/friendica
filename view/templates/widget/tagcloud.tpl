{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<nav>
	<span id="tagblock-inflated" class="widget inflated fakelink">
		<button class="fakelink" onclick="openCloseWidget('tagblock', 'tagblock-inflated');" aria-expanded="false">
			<h3>
					<i class="ri ri-hashtag" aria-hidden="true"></i>
					{{$title}}
			</h3>
		</button>
	</span>
	<div id="tagblock" class="tagblock widget">
		<button class="fakelink" onclick="openCloseWidget('tagblock', 'tagblock-inflated');" aria-expanded="true">
			<h3>
					<i class="ri ri-hashtag" aria-hidden="true"></i>
					{{$title}}
			</h3>
		</button>

		<div class="tag-cloud tags">
			{{foreach $tags as $tag}}
				<a href="{{$tag.url}}" class="tag hashtag tag{{$tag.level}} label border border-default">#{{$tag.name}}</a>
			{{/foreach}}
		</div>
		<div class="tagblock-widget-end clear"></div>
	</div>
</nav>
<script>
	initWidget('tagblock', 'tagblock-inflated');
</script>
