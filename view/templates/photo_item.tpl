{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}

<div class="wall-item-outside-wrapper{{$indent}}" id="wall-item-outside-wrapper-{{$id}}">
	<div class="wall-item-photo-wrapper" id="wall-item-photo-wrapper-{{$id}}">
		<a href="{{$profile_url}}" title="View {{$name}}'s profile" class="wall-item-photo-link" id="wall-item-photo-link-{{$id}}">
		<img src="{{$thumb}}" class="wall-item-photo" id="wall-item-photo-{{$id}}" style="height: 80px; width: 80px;" alt="{{$name}}" /></a>
	</div>

	<div class="wall-item-wrapper" id="wall-item-wrapper-{{$id}}">
		<a href="{{$profile_url}}" title="View {{$name}}'s profile" class="wall-item-name-link"><span class="wall-item-name" id="wall-item-name-{{$id}}">{{$name}}</span></a>
		<div class="wall-item-ago"  id="wall-item-ago-{{$id}}">{{$ago}}</div>
	</div>
	<div class="wall-item-content" id="wall-item-content-{{$id}}">
		<div class="wall-item-title" id="wall-item-title-{{$id}}">{{$title}}</div>
		<div class="wall-item-body" id="wall-item-body-{{$id}}" dir="auto">{{$body}}</div>
	</div>
	
	{{if $drop.dropping }}
		<div class="wall-item-delete-wrapper" id="wall-item-delete-wrapper-{{$id}}">
			<a href="item/drop/{{$id}}" onclick="return confirmDelete();" class="icon drophide" title="{{$drop.delete}}" onmouseover="imgbright(this);" onmouseout="imgdull(this);"></a>
		</div>
		<div class="wall-item-delete-end"></div>
	{{/if}}

	<div class="wall-item-wrapper-end"></div>
	<div class="wall-item-comment-separator"></div>
	{{$comment}}

<div class="wall-item-outside-wrapper-end{{$indent}}"></div>
</div>

