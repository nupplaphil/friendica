{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<nav>
	{{if $type == "channel"}}
		{{assign var="icon" value="ri-layout-grid-line"}}
	{{else if $type == "accounttype"}}
		{{assign var="icon" value="ri-umbrella-line"}}
	{{else if $type == "rel"}}
		{{assign var="icon" value="ri-arrow-left-right-line"}}
	{{else if $type == "circle"}}
		{{assign var="icon" value="ri-account-circle-line"}}
	{{else}} {{* fallback to type="file" *}}
		{{assign var="icon" value="ri-folder-line"}}
	{{/if}}
	<span id="{{$type}}-sidebar-inflated" class="widget inflated fakelink">
		<button class="fakelink" onclick="openCloseWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');" aria-expanded="false">
			<h3>
				<i class="ri {{$icon}}" aria-hidden="true"></i>
				{{$title}}
			</h3>
		</button>
	</span>
	<div id="{{$type}}-sidebar" class="widget">
		<button class="fakelink" onclick="openCloseWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');" aria-expanded="true">
			<h3>
				<i class="ri {{$icon}}" aria-hidden="true"></i>
			{{$title}}
			</h3>
		</button>
		<div id="{{$type}}-desc">{{$desc nofilter}}</div>
		<ul class="{{$type}}-ul">
			{{if $all_label}}
				<li {{if !is_null($selected) && !$selected}}class="selected" {{/if}}><a href="{{$base}}" class="{{$type}}-link{{if !$selected}} {{$type}}-selected{{/if}} {{$type}}-all">{{$all_label}}</a>
				</li>
			{{/if}}
			{{foreach $options as $option}}
				<li {{if $selected == $option.ref}}class="selected" {{/if}}><a href="{{$base}}{{$type}}={{$option.ref}}" class="{{$type}}-link{{if $selected == $option.ref}} {{$type}}-selected{{/if}}">{{$option.name}}</a>
				</li>
			{{/foreach}}
		</ul>
	</div>
</nav>
<script>
	initWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');
</script>
