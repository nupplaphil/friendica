# Database Tables

{{foreach $tables as $table}}
| {{$table.name nofilter}} | {{$table.comment nofilter}} |
{{/foreach}}
