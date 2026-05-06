SET
FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE be_users;
TRUNCATE TABLE be_groups;
TRUNCATE TABLE pages;
TRUNCATE TABLE tt_content;

-- argon2id hash of the cleartext password "Password.1" (TYPO3 v13/v14 default
-- backend hashing algorithm). Same hash used for the admin and editor users.
INSERT INTO be_users (uid, pid, tstamp, username, password, admin, disable, starttime, endtime, options, crdate,
											workspace_perms, deleted, TSconfig, lastlogin, workspace_id, db_mountpoints, usergroup, realName)
VALUES (1, 0, 1366642540, 'admin',
				'$argon2id$v=19$m=65536,t=4,p=1$V3lYekJpeVp6TEdPbmU2NQ$akxm34MxFiRQ717lGkRA3rrxL2zYdA9cpVATldUIx7k', 1, 0, 0, 0,
				0, 1366642540, 1, 0, '', 1371033743, 0, '0', '0', 'Klaus Admin'),
			 (2, 0, 1452944912, 'editor',
				'$argon2id$v=19$m=65536,t=4,p=1$V3lYekJpeVp6TEdPbmU2NQ$akxm34MxFiRQ717lGkRA3rrxL2zYdA9cpVATldUIx7k', 0, 0, 0, 0,
				0, 1452944912, 1, 0, '', 1452944915, 0, '1', '1', '');

INSERT INTO be_groups (uid, pid, tstamp, title, tables_modify, crdate, subgroup)
VALUES (1, 0, 1452959228, 'editor-group', 'pages', 1452959228, ''),
			 (2, 0, 1452959228, 'some test group', 'pages', 1452959228, '1');

INSERT INTO `pages` (`uid`, `pid`, `tstamp`, `crdate`, `deleted`, `hidden`, `starttime`, `endtime`, `fe_group`,
										 `sorting`, `rowDescription`, `editlock`, `sys_language_uid`, `l10n_parent`, `l10n_source`,
										 `l10n_state`, `l10n_diffsource`, `t3ver_oid`, `t3ver_wsid`, `t3ver_state`, `t3ver_stage`,
										 `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `SYS_LASTCHANGED`,
										 `shortcut`, `content_from_pid`, `mount_pid`, `sitemap_priority`, `doktype`, `title`, `slug`,
										 `TSconfig`, `php_tree_stop`, `categories`, `layout`, `extendToSubpages`, `nav_title`, `nav_hide`,
										 `subtitle`, `target`, `link`, `lastUpdated`, `newUntil`, `cache_timeout`, `cache_tags`,
										 `no_search`, `shortcut_mode`, `keywords`, `description`, `abstract`, `author`, `author_email`,
										 `media`, `is_siteroot`, `mount_pid_ol`, `module`, `l18n_cfg`, `backend_layout`,
										 `backend_layout_next_level`, `tsconfig_includes`, `seo_title`, `no_index`, `no_follow`,
										 `sitemap_changefreq`, `canonical_link`, `og_title`, `og_description`, `og_image`, `twitter_title`,
										 `twitter_description`, `twitter_image`, `twitter_card`, `tx_revealjseditor_theme`)
VALUES (1, 0, 0, 0, 0, 0, 0, 0, '0', 256, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.5, 1,
				'Reveal Demo Root', '/', '', 0, 0, 0, 0, '', 0, '', '', '', 0, 0, 0, '', 0, 0, NULL, NULL, NULL, '', '', 0, 1,
				0, '', 0, '', '', '', '', 0, 0, '', '', '', NULL, 0, '', NULL, 0, '', 'black'),
			 (2, 1, 0, 0, 0, 0, 0, 0, '0', 768, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.5, 1,
				'THE CÄMP', '/subpage-germany', NULL, 0, 0, 0, 0, '', 0, '', '', '', 0, 0, 0, '', 0, 0, NULL, NULL, NULL, '',
				'', 0, 0, 0, '', 0, '', '', '', '', 0, 0, '', '', '', NULL, 0, '', NULL, 0, '', 'black'),
			 (10, 1, 0, 0, 0, 0, 0, 0, '0', 512, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.5,
				1731, 'Demo Slides', '/demo-slides', NULL, 0, 0, 0, 0, '', 0, '', '', '', 0, 0, 0, '', 0, 0, NULL, NULL, NULL,
				'', '', 0, 0, 0, '', 0, 'pagets__revealjs', '', '', '', 0, 0, '', '', '', NULL, 0, '', NULL, 0, '', 'black'),
			 (11, 1, 1778087331, 1778070899, 0, 0, 0, 0, '', 256, NULL, 0, 0, 0, 0, NULL,
				X'7B225453636F6E666967223A22222C226162737472616374223A22222C226261636B656E645F6C61796F7574223A22222C226261636B656E645F6C61796F75745F6E6578745F6C6576656C223A22222C22646F6B74797065223A22222C22656469746C6F636B223A22222C22656E6474696D65223A22222C22657874656E64546F5375627061676573223A22222C2266655F67726F7570223A22222C2268696464656E223A22222C226B6579776F726473223A22222C226C61796F7574223A22222C226E61765F68696465223A22222C226E61765F7469746C65223A22222C226E6577556E74696C223A22222C22736C7567223A22222C22737461727474696D65223A22222C227375627469746C65223A22222C227469746C65223A22222C227473636F6E6669675F696E636C75646573223A22222C2274785F72657665616C6A73656469746F725F7468656D65223A22227D',
				0, 0, 0, 0, 1, 0, 31, 27, 0, 1778098053, 0, 0, 0, 0.5, 1731, 'Lets Present This',
				'/subpage-germany/lets-present-this', NULL, 0, 0, 0, 0, '', 0, '', '', '', 0, 0, 0, '', 0, 0, NULL, NULL, NULL,
				'', '', 0, 0, 0, '', 0, 'pagets__revealjs', 'pagets__revealjs', '', '', 0, 0, '', '', '', NULL, 0, '', NULL, 0,
				'', 'the-caemp');

INSERT INTO `tt_content` (`uid`, `pid`, `tstamp`, `crdate`, `deleted`, `hidden`, `starttime`, `endtime`, `fe_group`,
													`sorting`, `rowDescription`, `editlock`, `sys_language_uid`, `l18n_parent`, `l10n_source`,
													`l10n_state`, `l18n_diffsource`, `t3ver_oid`, `t3ver_wsid`, `t3ver_state`, `t3ver_stage`,
													`frame_class`, `colPos`, `table_caption`, `CType`, `categories`, `layout`,
													`space_before_class`, `space_after_class`, `date`, `header`, `header_layout`,
													`header_position`, `header_link`, `subheader`, `bodytext`, `image`, `assets`, `imagewidth`,
													`imageheight`, `imageorient`, `imageborder`, `image_zoom`, `imagecols`, `pages`, `recursive`,
													`media`, `records`, `sectionIndex`, `linkToTop`, `pi_flexform`, `selected_categories`,
													`category_field`, `bullets_type`, `cols`, `table_class`, `table_delimiter`, `table_enclosure`,
													`table_header_position`, `table_tfoot`, `file_collections`, `filelink_size`,
													`filelink_sorting`, `filelink_sorting_direction`, `target`, `uploads_description`,
													`uploads_type`, `tx_container_parent`, `tx_revealjseditor_transition`,
													`tx_revealjseditor_data_state`)
VALUES (1, 1, 1778062567, 1778062567, 0, 0, 0, 0, '0', 0, NULL, 0, 0, 0, 0, NULL, NULL, 0, 0, 0, 0, 'default', 0, NULL,
				'text', 0, 0, '', '', 0, 'Welcome to your default website', 0, '', '', '',
				'<p>This website is made with <a href=\"https://typo3.org\" target=\"_blank\">TYPO3</a>.</p>', 0, 0, NULL, NULL,
				0, 0, 0, 2, NULL, 0, 0, NULL, 1, 0, NULL, NULL, '', 0, 0, '', 124, 0, 0, 0, NULL, 0, '', '', '', 0, 0, 0,
				'slide', ''),
			 (101, 10, 1778096901, 0, 0, 0, 0, 0, '0', 0, NULL, 0, 0, 0, 0, NULL, X'7B22636F6C506F73223A22227D', 0, 0, 0, 0,
				'default', 0, NULL, 'text', 0, 0, '', '', 0, 'Hello', 0, '', '', '', '<p>Welcome to reveal.js!</p>', 0, 0, NULL,
				NULL, 0, 0, 0, 2, NULL, 0, 0, NULL, 1, 0, NULL, NULL, '', 0, 0, '', 124, 0, 0, 0, NULL, 0, '', '', '', 0, 0,
				100, 'slide', ''),
			 (105, 11, 1778098053, 1778071159, 0, 0, 0, 0, '', 512, '', 0, 0, 0, 0, NULL,
				X'7B224354797065223A22222C22617373657473223A22222C22626F647974657874223A22222C2263617465676F72696573223A22222C22636F6C506F73223A22222C2264617465223A22222C22656469746C6F636B223A22222C22656E6474696D65223A22222C2266655F67726F7570223A22222C226672616D655F636C617373223A22222C22686561646572223A22222C226865616465725F6C61796F7574223A22222C226865616465725F6C696E6B223A22222C226865616465725F706F736974696F6E223A22222C2268696464656E223A22222C22696D6167655F7A6F6F6D223A22222C22696D616765626F72646572223A22222C22696D616765636F6C73223A22222C22696D616765686569676874223A22222C22696D6167656F7269656E74223A22222C22696D6167657769647468223A22222C226C61796F7574223A22222C226C696E6B546F546F70223A22222C22726F774465736372697074696F6E223A22222C2273656374696F6E496E646578223A22222C2273706163655F61667465725F636C617373223A22222C2273706163655F6265666F72655F636C617373223A22222C22737461727474696D65223A22222C22737562686561646572223A22222C2274785F72657665616C6A73656469746F725F646174615F7374617465223A22222C2274785F72657665616C6A73656469746F725F7472616E736974696F6E223A22227D',
				0, 0, 0, 0, 'default', 0, NULL, 'text', 0, 0, '', '', 0, '... and what are WebComponents?', 1, '', '', '',
				'<p><strong>In short:</strong></p>\r\n<p>WebComponents allow you to define/invent your own custom HTML elements.</p>',
				0, 1, NULL, NULL, 8, 0, 0, 2, '', 0, 0, '', 1, 0, NULL, '0', '', 0, 0, '', 124, 0, 0, 0, '', 0, '', '', '', 0,
				0, 0, 'slide', ''),
			 (112, 11, 1778097953, 1778089720, 0, 0, 0, 0, '', 64, '', 0, 0, 0, 0, NULL,
				X'7B224354797065223A22222C22626F647974657874223A22222C2263617465676F72696573223A22222C22636F6C506F73223A22222C2264617465223A22222C22656469746C6F636B223A22222C22656E6474696D65223A22222C2266655F67726F7570223A22222C226672616D655F636C617373223A22222C22686561646572223A22222C226865616465725F6C61796F7574223A22222C226865616465725F6C696E6B223A22222C226865616465725F706F736974696F6E223A22222C2268696464656E223A22222C226C61796F7574223A22222C226C696E6B546F546F70223A22222C22726F774465736372697074696F6E223A22222C2273656374696F6E496E646578223A22222C2273706163655F61667465725F636C617373223A22222C2273706163655F6265666F72655F636C617373223A22222C22737461727474696D65223A22222C22737562686561646572223A22222C2274785F72657665616C6A73656469746F725F646174615F7374617465223A22222C2274785F72657665616C6A73656469746F725F7472616E736974696F6E223A22227D',
				0, 0, 0, 0, 'default', 0, NULL, 'text', 0, 0, '', '', 0, 'What is Lit?', 1, '', '', '',
				'<ul><li data-list-item-id=\"e27ed0e40c68535d6af071c6f6fa63814\">A small wrapper around the WebComponents standard</li><li data-list-item-id=\"e78db5332542925a6f90e590de9edb018\">Lit enables you to write WebComponents in a convenient and productive way</li></ul>',
				0, 0, NULL, NULL, 0, 0, 0, 2, NULL, 0, 0, NULL, 1, 0, NULL, NULL, '', 0, 0, '', 124, 0, 0, 0, NULL, 0, '', '',
				'', 0, 0, 0, 'slide', ''),
			 (115, 11, 1778097812, 1778093675, 0, 0, 0, 0, '', 32, '', 0, 0, 0, 0, NULL,
				X'7B224354797065223A22222C22626F647974657874223A22222C2263617465676F72696573223A22222C22636F6C506F73223A22222C2264617465223A22222C22656469746C6F636B223A22222C22656E6474696D65223A22222C2266655F67726F7570223A22222C226672616D655F636C617373223A22222C22686561646572223A22222C226865616465725F6C61796F7574223A22222C226865616465725F6C696E6B223A22222C226865616465725F706F736974696F6E223A22222C2268696464656E223A22222C22696D616765223A22222C22696D6167655F7A6F6F6D223A22222C22696D616765626F72646572223A22222C22696D616765636F6C73223A22222C22696D616765686569676874223A22222C22696D6167656F7269656E74223A22222C22696D6167657769647468223A22222C226C61796F7574223A22222C226C696E6B546F546F70223A22222C22726F774465736372697074696F6E223A22222C2273656374696F6E496E646578223A22222C2273706163655F61667465725F636C617373223A22222C2273706163655F6265666F72655F636C617373223A22222C22737461727474696D65223A22222C22737562686561646572223A22222C2274785F72657665616C6A73656469746F725F646174615F7374617465223A22222C2274785F72657665616C6A73656469746F725F7472616E736974696F6E223A22227D',
				0, 0, 0, 0, 'default', 0, NULL, 'revealjs_slide_cover', 0, 0, '', '', 0, 'The Cämp', 1, '', '', 'Lit it Up!',
				'<ol><li data-list-item-id=\"e3c33b42410989e2682f750279345aedf\">No build, no framework, no external dependencies.</li><li data-list-item-id=\"eea322a8bfb483247538201f4f8383bbc\">Re-use WebComponents shipped with TYPO3.</li></ol>',
				1, 0, NULL, NULL, 0, 0, 0, 2, NULL, 0, 0, NULL, 1, 0, NULL, NULL, '', 0, 0, '', 124, 0, 0, 0, NULL, 0, '', '',
				'', 0, 0, 0, 'convex', '');

SET
FOREIGN_KEY_CHECKS = 1;
