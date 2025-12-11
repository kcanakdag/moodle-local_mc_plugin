<?php
namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Text config setting that doesn't show "Default: Empty".
 */
class setting_configtext_nodefault extends \admin_setting_configtext {

    public function output_html($data, $query = '') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        $context = (object) [
            'size' => $this->size,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $data,
            'forceltr' => $this->get_force_ltr(),
            'readonly' => $this->is_readonly(),
        ];
        $element = $OUTPUT->render_from_template('core_admin/setting_configtext', $context);

        // Pass null for defaultinfo to hide "Default: Empty"
        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', null, $query);
    }
}
