UPDATE `version` SET `app_ver` = '1.7.0-alpha2', `XmdsVersion` = 4;
UPDATE `setting` SET `value` = 0 WHERE `setting` = 'PHONE_HOME_DATE';
UPDATE `version` SET `DBVersion` = '81';