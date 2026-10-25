<?php
return array(
	'music.meta.name' => 'Music',
	'music.meta.title' => 'Library and player',
	'music.meta.desc' => 'Library, playlists, queue and player for audio the current account can read. Playlists default to every song. Online lyrics stay off unless enabled.',
	'music.config.onlineLyrics' => 'Online lyrics',
	'music.config.onlineLyricsDesc' => 'Off by default. When on, the sources below are queried only if no local or embedded lyrics exist.',
	'music.config.lyricSources' => 'Lyric sources',
	'music.config.lyricSourcesDesc' => 'Queried in the order AMLL, Kugou, lrclib. Word-timed lyrics are preferred; line-timed ones are used only when none is found. Title, artist and duration are sent to the selected services. AMLL downloads its index from GitHub once a day.',
	'music.config.sourceAmll' => 'AMLL TTML DB (word-timed, community)',
	'music.config.sourceKugou' => 'Kugou Music (word-timed KRC)',
	'music.config.sourceLrclib' => 'lrclib.net (line-timed)',
	'music.config.scanVideo' => 'Scan videos',
	'music.config.scanVideoDesc' => 'Include webm, mp4, mkv and similar files. Existing library items are kept if this is off.',
	'music.config.scanBatch' => 'Scan batch size',
	'music.config.scanBatchDesc' => 'Media files to tag per task. Suggested range 20–200.',
	'music.lib.unknownArtist' => 'Unknown artist',
	'music.lib.unknownAlbum' => 'Unknown album',
);
