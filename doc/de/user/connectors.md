---
title: Konnektoren
tags:
  - user
---
# Konnektoren (Connectors) 

Konnektoren erlauben es dir, dich mit anderen sozialen Netzwerken zu verbinden. 
Konnektoren werden nur bei bestehenden Twitter und GNU Social-Accounts benötigt. 
Außerdem gibt es einen Konnektor, um deinen E-Mail-Posteingang zu nutzen.
Wenn du keinen eigenen Knoten betreibst und wissen willst, ob der server deiner Wahl diese Konnektoren installiert hat, kannst du dich darüber auf der Seite '&lt;domain_des_friendica-servers&gt;/friendica' informieren.

Sind die Netzwerk-Konnektoren auf deinem System installiert sind, kannst du mit den folgenden Links die Einstellungsseiten besuchen und für deinen Account konfigurieren:

* Twitter
* GNU Social
* Email

# Anleitung

um sich mit Personen in bestimmten Netzwerken zu verbinden.

## Friendica

du kannst dich verbinden, indem du die Adresse deiner Identität (&lt;dein_nick&gt;@&lt;dein_friendica-host&gt;) auf der "Verbinden"-Seite des Friendica-Nutzers eingibst. 
Ebenso kannst du deren Identitäts-Adresse in der "Verbinden"-Box auf deiner ["Kontakt"-Seite](contacts) eingeben.

## Diaspora

Füge die Diaspora-Identitäts-Adresse (z.B. name@diasporapod.com)auf deiner ["Kontakte"-Seite](contacts) in das Feld "Neuen Kontakt hinzufügen" ein. 

## GNU Social

Dieses Netzwerk wird als "federated social web" bzw. "OStatus"-Kontakte bezeichnet.

Bitte beachte, dass es **keine** Einstellungen zur Privatsphäre im OStatus-Netzwerk gibt. 
**Jede** Nachricht, die an eines dieser OStatus-Mitglieder verschickt wird, ist für jeden auf der Welt sichtbar; alle Privatsphäreneinstellungen verlieren ihre Wirkung. 
Diese Nachrichten erscheinen ebenfalls in öffentlichen Suchergebnissen.

Da die OStatus-Kommunikation keine Authentifizierung benutzt, können OStatus-Nutzer *keine* Nachrichten empfangen, wenn du in deinen Privatsphäreneinstellungen "Profil und Nachrichten vor Unbekannten verbergen" wählst.

Um dich mit einem OStatus-Mitglied zu verbinden, trage deren Profil-URL oder Identitäts-Adresse auf deiner ["Kontakte"-Seite](contacts) in das Feld "Neuen Kontakt hinzufügen" ein.

Der GNU Social-Konnektor kann genutzt werden, wenn du Beiträge schreiben willst, die auf einer OStatus-Seite über einen existierenden OStatus-Account erscheinen sollen.

Das ist nicht notwendig, wenn du OStatus-Mitgliedern von Friendica aus folgst und diese dir auch folgen, indem sie auf deiner Kontaktseite ihre eigene Identitäts-Adresse eingeben.

## Blogger, Wordpress, RSS feeds, andere Webseiten

Trage die URL auf deiner "Kontakte"-Seite (`https://your-site.info/contacts`) in das Feld "Neuen Kontakt hinzufügen" ein. 
du hast keine Möglichkeit, diesen Kontakten zu antworten.

Das erlaubt dir, dich mit Millionen von Seiten im Internet zu _verbinden_. 
Alles, was dafür nötig ist, ist, dass die Seite einen Feed im RSS- oder Atom Syndication-Format nutzt und welches einen Autor und ein Bild zur Seite liefert. 

## Twitter

Um einem Twitter-Nutzer zu folgen, trage die URL der Hauptseite des Twitter-Accounts auf deiner "Kontakte"-Seite in das Feld "Neuen Kontakt hinzufügen" ein. 
Um zu antworten, musst du den Twitter-Konnektor installieren und über deinen eigenen Status-Editor antworten. 
Beginne deine Nachricht mit @twitternutzer, ersetze das aber durch den richtigen Twitter-Namen.

## Email

Konfiguriere den E-Mail-Konnektor auf deiner Einstellungsseite (`https://your-site.info/settings`). 
Wenn du das gemacht hast, kannst du auf deiner "Kontakte"-Seite die E-Mail-Adresse in das Feld "Neuen Kontakt hinzufügen" eintragen. 
Diese E-Mail-Adresse muss jedoch bereits mit einer Nachricht in deinem E-Mail-Posteingang auf dem Server liegen. 
du hast die Möglichkeit, E-Mail-Kontakte in deine privaten Unterhaltungen einzubeziehen.
