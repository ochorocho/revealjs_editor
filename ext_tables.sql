--
-- Override TCA-derived column widths for the page-level reveal.js options.
--
-- Why this file exists at all: the schema analyser turns every TCA
-- type=input / type=select column into VARCHAR(255). 18 such columns
-- end up on `pages` for the page-level reveal.js options. Combined with
-- the ~35 VARCHAR(255) columns already on `pages` (title, nav_title,
-- subtitle, slug, …), a fresh CREATE TABLE under utf8mb4 + InnoDB DYNAMIC
-- row format with innodb_strict_mode=ON exceeds the 8126-byte
-- logical-row limit (sum-of-max-lengths check) and MariaDB raises
-- "Row size too large (> 8126)". Locally this can stay hidden — columns
-- added incrementally via ALTER TABLE bypass the strict CREATE-TABLE
-- size check — but CI always creates the schema from scratch and the
-- limit fires every time on the `test` database setup.
--
-- The widths chosen below are the smallest that still fit each option's
-- enumerated values (controlslayout = "bottom-right", navigationmode =
-- "linear" / "grid" / "default", transition keywords, etc.). The select
-- box validates input anyway, so there's no truncation risk.
--
-- type=number columns are NOT listed: the schema analyser hardcodes
-- INT(11) NOT NULL DEFAULT 0 for them regardless of TCA defaults. type=check
-- columns use the analyser's SMALLINT default (2 bytes, picks up TCA
-- `default`). Only the type=input / type=select varchars need help.
--
-- tt_content's 14 reveal.js varchars fit under the limit on their own
-- table — no overrides needed there.
--
CREATE TABLE pages (
    tx_revealjseditor_theme                VARCHAR(32)  DEFAULT 'black'        NOT NULL,
    tx_revealjseditor_controlslayout       VARCHAR(16)  DEFAULT 'bottom-right' NOT NULL,
    tx_revealjseditor_controlsbackarrows   VARCHAR(16)  DEFAULT 'faded'        NOT NULL,
    tx_revealjseditor_slidenumber          VARCHAR(16)  DEFAULT 'false'        NOT NULL,
    tx_revealjseditor_showslidenumber      VARCHAR(16)  DEFAULT 'all'          NOT NULL,
    tx_revealjseditor_navigationmode       VARCHAR(16)  DEFAULT 'default'      NOT NULL,
    tx_revealjseditor_transition           VARCHAR(16)  DEFAULT 'slide'        NOT NULL,
    tx_revealjseditor_transitionspeed      VARCHAR(16)  DEFAULT 'default'      NOT NULL,
    tx_revealjseditor_backgroundtransition VARCHAR(16)  DEFAULT 'fade'         NOT NULL,
    tx_revealjseditor_autoanimateeasing    VARCHAR(64)  DEFAULT 'ease'         NOT NULL,
    tx_revealjseditor_autoanimateduration  VARCHAR(16)  DEFAULT '1.0'          NOT NULL,
    tx_revealjseditor_autoplaymedia        VARCHAR(16)  DEFAULT 'default'      NOT NULL,
    tx_revealjseditor_preloadiframes       VARCHAR(16)  DEFAULT 'default'      NOT NULL,
    tx_revealjseditor_view                 VARCHAR(16)  DEFAULT 'default'      NOT NULL,
    tx_revealjseditor_scrolllayout         VARCHAR(16)  DEFAULT 'full'         NOT NULL,
    tx_revealjseditor_scrollsnap           VARCHAR(16)  DEFAULT 'mandatory'    NOT NULL,
    tx_revealjseditor_scrollprogress       VARCHAR(16)  DEFAULT 'auto'         NOT NULL,
    tx_revealjseditor_author               VARCHAR(255) DEFAULT ''             NOT NULL
);
