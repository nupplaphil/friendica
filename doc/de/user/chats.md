---
title: Chats
tags:
  - user
---
# Chats

du hast derzeit zwei Möglichkeiten, einen Chat auf deiner Friendica-Seite zu betreiben

* IRC - Internet Relay Chat
* Jappix

## IRC Addon

Sobald das Addon aktiviert ist, kannst du den Chat unter `https://deineSeite.de/irc` finden.
Beachte aber, dass dieser Chat auch ohne Anmeldung auf deiner Seite zugänglich ist und somit auch Fremde diesen Chat mitnutzen können.

Wenn du dem Link folgst, dann kommst du zum Anmeldefenster des IR-Chats.
Wähle nun einen Spitznamen (Nickname) und wähle einen Raum aus, in dem du chatten willst.
Hier kannst du jeden Namen eingeben.
Es kann also auch #tollerChatdessenNamenurichkenne sein.
Gib als Nächstes noch die Captchas ein, um zu zeigen, dass es sich bei dir um einen Menschen handelt und klicke auf "Connect".

Im nächsten Fenster siehst du zunächst viel Text beim Verbindungsaufbau, der allerdings für dich nicht weiter von Bedeutung ist.
Anschließend öffnet sich das Chat-Fenster.
In den ersten Zeilen wird dir dein Name und deine aktuelle IP-Adresse angezeigt.
Rechts im Fenster siehst du alle Teilnehmer des Chats.
Unten hast du ein Eingabefeld, um Beiträge zu schreiben.

Weiter Informationen zu IRC findest du zum Beispiel auf <a href="http://wiki.ubuntuusers.de/IRC" target="_blank" rel="noopener noreferrer">ubuntuusers.de</a>, in <a href="https://de.wikipedia.org/wiki/Internet_Relay_Chat" target="_blank" rel="noopener noreferrer">Wikipedia</a> oder bei <a href="http://www.irchelp.org/" target="_blank" rel="noopener noreferrer">icrhelp.org</a> (in Englisch).

## Jappix Mini

Das Jappix Mini Addon erlaubt das Erstellen einer Chatbox für Jabber/XMPP-Kontakte.
Ein Jabber/XMPP Account sollte vor der Installation bereits vorhanden sein.
Die ausführliche Anleitung dazu und eine Kontrolle, ob du nicht sogar schon über deinen E-Mail-Anbieter einen Jabber-Account hast, findest du unter <a href="http://einfachjabber.de" target="_blank" rel="noopener noreferrer">einfachjabber.de</a>.

Einige Server zum Anmelden eines neuen Accounts:

* [https://jappix.com](https://jappix.com)
* [https://www.jabme.de](https://www.jabme.de)
* [http://www.jabber.de](http://www.jabber.de)
* oder die Auswahl von [http://xmpp.net](http://xmpp.net) nutzen.

## 1. Grundsätzliches

Als Erstes musst du die aktuellste Version herunterladen:

Per Git:
```sh
cd /var/www/<Pfad zu deiner friendica-Installation>/addon
git pull
```

oder als normaler Download von hier: https://github.com/friendica/friendica-addons/blob/stable/jappixmini.tgz (auf „view raw“ klicken)

Entpacke diese Datei (ggf. den entpackten Ordner in „jappixmini“ umbenennen) und lade sowohl den entpackten Ordner komplett als auch die .tgz Datei in den Addon Ordner deiner Friendica Installation hoch.

Nach dem Upload gehts in den Friendica Adminbereich und dort zu den Addons.
Aktiviere das Jappixmini Addon und gehe anschließend über die Addons Seitenleiste (dort wo auch die Twitter-, Impressums-, GNU Social-, usw. Einstellungen gemacht werden) zu den Jappix Grundeinstellungen.

Setze hier den Haken zur Aktivierung des BOSH Proxys.
Weiter gehts in den Einstellungen deines Friendica Accounts.

## 2. Einstellungen

Gehe bitte zu den Addon-Einstellungen in deinen Konto-Einstellungen (Account Settings).
Scrolle ein Stück hinunter bis zu den Jappix Mini Addon settings.

Aktiviere hier zuerst das Addon.

Trage nun deinen Jabber/XMPP Namen ein, ebenfalls die entsprechende Domain bzw. den Server (ohne http, also z. B. einfach so: jappix.com).
Um das JavaScript zum Chatten im Browser verwenden zu können, benötigst du einen BOSH Proxy.
Entweder betreibst du deinen eigenen (s. Dokumentation deines XMPP Servers) oder du verwendest einen öffentlichen BOSH Proxy.
Beachte aber, dass der Betreiber dieses Proxies den kompletten Datenverkehr über den Proxy mitlesen kann.
Siehe dazu auch die „Configuration Help“ unter den Eingabefeldern.
Gebe danach noch dein Passwort an, und damit ist eigentlich schon fast alles geschafft.
Die weiteren Einstellmöglichkeiten bleiben dir überlassen, sind also optional.
Jetzt noch auf „senden“ klicken und fertig.

deine Chatbox sollte jetzt irgendwo unten rechts im Browserfenster „kleben“.
Falls du manuell Kontakte hinzufügen möchtest, einfach den „Add Contact“-Knopf nutzen.

Viel Spass beim Chatten!
