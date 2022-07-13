---
title: {{$name nofilter}}
tags:
  - database
  - table
  - developer
---
# Table {{$name nofilter}}

{{$comment nofilter}}

## Fields

{{foreach $fields as $field}}
| {{$field.name nofilter}} | {{$field.comment nofilter}} | {{$field.type nofilter}} | {{$field.null nofilter}} | {{$field.primary nofilter}} | {{$field.default nofilter}} | {{$field.extra nofilter}} |
{{/foreach}}

## Indexes

{{foreach $indexes as $index}}
| {{$index.name nofilter}} | {{$index.fields nofilter}} |
{{/foreach}}

{{if $has_foreign}}
## Foreign Keys

{{foreach $foreign as $key}}
| {{$key.field nofilter}} | {{$key.targettable nofilter}} | {{$key.targetfield nofilter}} |
{{/foreach}}
{{/if}}

Return to [database documentation](./index.md)
