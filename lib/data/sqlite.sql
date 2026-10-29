CREATE TABLE IF NOT EXISTS "plugin_music_track" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "sourceID" integer NOT NULL,
  "fileID" integer NOT NULL,
  "name" varchar(250) NOT NULL,
  "ext" varchar(16) NOT NULL,
  "size" integer NOT NULL DEFAULT 0,
  "title" varchar(250) NOT NULL DEFAULT '',
  "artist" varchar(250) NOT NULL DEFAULT '',
  "album" varchar(250) NOT NULL DEFAULT '',
  "genre" varchar(120) NOT NULL DEFAULT '',
  "year" varchar(16) NOT NULL DEFAULT '',
  "trackNo" integer NOT NULL DEFAULT 0,
  "duration" integer NOT NULL DEFAULT 0,
  "hasCover" integer NOT NULL DEFAULT 0,
  "lyricOffset" integer NOT NULL DEFAULT 0,
  "excluded" integer NOT NULL DEFAULT 0,
  "favorite" integer NOT NULL DEFAULT 0,
  "sourceModifyTime" integer NOT NULL DEFAULT 0,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "idx_music_user_source" ON "plugin_music_track" ("userID","sourceID");
CREATE INDEX IF NOT EXISTS "idx_music_user_artist" ON "plugin_music_track" ("userID","artist");
CREATE INDEX IF NOT EXISTS "idx_music_user_album" ON "plugin_music_track" ("userID","album");
CREATE TABLE IF NOT EXISTS "plugin_music_list" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "name" varchar(80) NOT NULL,
  "createTime" integer NOT NULL
);
CREATE INDEX IF NOT EXISTS "idx_music_list_user" ON "plugin_music_list" ("userID");
CREATE TABLE IF NOT EXISTS "plugin_music_list_item" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "listID" integer NOT NULL,
  "userID" integer NOT NULL,
  "sourceID" integer NOT NULL,
  "sort" integer NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX IF NOT EXISTS "idx_music_list_source" ON "plugin_music_list_item" ("listID","sourceID");
