{include file='../CRM/Upgrade/4.4.alpha1.msg_template/civicrm_msg_template.tpl'}

-- CRM-12357
SELECT @option_group_id_cvOpt := max(id) FROM civicrm_option_group WHERE name = 'contact_view_options';
SELECT @max_val := MAX(ROUND(op.value)) FROM civicrm_option_value op  WHERE op.option_group_id  = @option_group_id_cvOpt;
SELECT @max_wt := MAX(ROUND(val.weight)) FROM civicrm_option_value val WHERE val.option_group_id = @option_group_id_cvOpt;

INSERT INTO
   `civicrm_option_value` (`option_group_id`, {localize field='label'}`label`{/localize}, `value`, `name`, `grouping`, `filter`, `is_default`, `weight`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `visibility_id`)
VALUES
  (@option_group_id_cvOpt, {localize}'{ts escape="sql"}Mailings{/ts}'{/localize}, @max_val+1, 'CiviMail', NULL, 0, NULL,  @max_wt+1, 0, 0, 1, NULL, NULL);

INSERT INTO civicrm_setting
  (domain_id, contact_id, is_domain, group_name, name, value)
VALUES
  ({$domainID}, NULL, 1, 'Mailing Preferences', 'write_activity_record', '{serialize}1{/serialize}');