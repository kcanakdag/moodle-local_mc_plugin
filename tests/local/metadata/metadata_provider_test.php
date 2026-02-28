<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for metadata providers.
 *
 * Tests that each provider returns the correct data structure and
 * handles empty results gracefully.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

/**
 * Test cases for metadata providers.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\metadata\courses_provider
 * @covers     \local_mc_plugin\local\metadata\roles_provider
 * @covers     \local_mc_plugin\local\metadata\groups_provider
 * @covers     \local_mc_plugin\local\metadata\cohorts_provider
 * @covers     \local_mc_plugin\local\metadata\badges_provider
 * @covers     \local_mc_plugin\local\metadata\certificates_provider
 * @covers     \local_mc_plugin\local\metadata\metadata_provider_factory
 */
final class metadata_provider_test extends \advanced_testcase {
    /**
     * Test courses_provider returns correct structure with data.
     */
    public function test_courses_provider_returns_data(): void {
        $this->resetAfterTest(true);

        $this->getDataGenerator()->create_course(['fullname' => 'Test Course', 'shortname' => 'TC1']);

        $provider = new courses_provider();
        $this->assertEquals('courses', $provider->get_type());

        $result = $provider->get_all();
        $this->assertNotEmpty($result);

        $course = $result[0];
        $this->assertArrayHasKey('id', $course);
        $this->assertArrayHasKey('shortname', $course);
        $this->assertArrayHasKey('fullname', $course);
        $this->assertArrayHasKey('category', $course);
        $this->assertIsInt($course['id']);
    }

    /**
     * Test courses_provider excludes the site-level course.
     */
    public function test_courses_provider_excludes_site_course(): void {
        $this->resetAfterTest(true);

        $result = (new courses_provider())->get_all();
        foreach ($result as $course) {
            $this->assertNotEquals(SITEID, $course['id']);
        }
    }

    /**
     * Test roles_provider returns correct structure.
     */
    public function test_roles_provider_returns_data(): void {
        $this->resetAfterTest(true);

        $provider = new roles_provider();
        $this->assertEquals('roles', $provider->get_type());

        $result = $provider->get_all();
        $this->assertNotEmpty($result);

        $role = $result[0];
        $this->assertArrayHasKey('id', $role);
        $this->assertArrayHasKey('shortname', $role);
        $this->assertArrayHasKey('name', $role);
        $this->assertArrayHasKey('archetype', $role);
        $this->assertIsInt($role['id']);
    }

    /**
     * Test groups_provider returns correct structure with data.
     */
    public function test_groups_provider_returns_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group A']);

        $provider = new groups_provider();
        $this->assertEquals('groups', $provider->get_type());

        $result = $provider->get_all();
        $this->assertNotEmpty($result);

        $group = $result[0];
        $this->assertArrayHasKey('id', $group);
        $this->assertArrayHasKey('name', $group);
        $this->assertArrayHasKey('courseid', $group);
        $this->assertArrayHasKey('course_name', $group);
        $this->assertIsInt($group['id']);
        $this->assertIsInt($group['courseid']);
    }

    /**
     * Test groups_provider returns empty array when no groups exist.
     */
    public function test_groups_provider_empty(): void {
        $this->resetAfterTest(true);

        $result = (new groups_provider())->get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test cohorts_provider returns correct structure with data.
     */
    public function test_cohorts_provider_returns_data(): void {
        $this->resetAfterTest(true);

        $this->getDataGenerator()->create_cohort(['name' => 'Test Cohort']);

        $provider = new cohorts_provider();
        $this->assertEquals('cohorts', $provider->get_type());

        $result = $provider->get_all();
        $this->assertNotEmpty($result);

        $cohort = $result[0];
        $this->assertArrayHasKey('id', $cohort);
        $this->assertArrayHasKey('name', $cohort);
        $this->assertArrayHasKey('idnumber', $cohort);
        $this->assertArrayHasKey('context', $cohort);
        $this->assertIsInt($cohort['id']);
    }

    /**
     * Test cohorts_provider returns empty array when no cohorts exist.
     */
    public function test_cohorts_provider_empty(): void {
        $this->resetAfterTest(true);

        $result = (new cohorts_provider())->get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test badges_provider returns empty when badges disabled.
     */
    public function test_badges_provider_disabled(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->enablebadges = false;

        $provider = new badges_provider();
        $this->assertEquals('badges', $provider->get_type());

        $result = $provider->get_all();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test badges_provider returns correct structure when enabled.
     */
    public function test_badges_provider_enabled(): void {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        $CFG->enablebadges = true;

        // Create an active badge directly in the database.
        $DB->insert_record('badge', (object) [
            'name' => 'Test Badge',
            'description' => 'A test badge.',
            'timecreated' => time(),
            'timemodified' => time(),
            'usercreated' => 2,
            'usermodified' => 2,
            'issuername' => 'Test Issuer',
            'issuerurl' => 'https://example.com',
            'issuercontact' => '',
            'expiredate' => null,
            'expireperiod' => null,
            'type' => 1,
            'courseid' => null,
            'message' => '',
            'messagesubject' => '',
            'attachment' => 0,
            'notification' => 0,
            'status' => 1,
            'nextcron' => null,
            'version' => '',
            'language' => 'en',
            'imageauthorname' => '',
            'imageauthoremail' => '',
            'imageauthorurl' => '',
            'imagecaption' => '',
        ]);

        $result = (new badges_provider())->get_all();
        $this->assertNotEmpty($result);

        $badge = $result[0];
        $this->assertArrayHasKey('id', $badge);
        $this->assertArrayHasKey('name', $badge);
        $this->assertArrayHasKey('type', $badge);
        $this->assertArrayHasKey('courseid', $badge);
        $this->assertIsInt($badge['id']);
        $this->assertContains($badge['type'], ['site', 'course']);
    }

    /**
     * Test certificates_provider returns empty when mod_customcert not installed.
     */
    public function test_certificates_provider_no_plugin(): void {
        $this->resetAfterTest(true);

        $provider = new certificates_provider();
        $this->assertEquals('certificates', $provider->get_type());

        $availability = \core_component::get_component_directory('mod_customcert');
        if ($availability === null || !file_exists($availability)) {
            $result = $provider->get_all();
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } else {
            $this->markTestSkipped('mod_customcert is installed; cannot test empty path.');
        }
    }

    /**
     * Test metadata_provider_factory returns correct provider types.
     */
    public function test_factory_returns_providers(): void {
        $types = metadata_provider_factory::get_supported_types();
        $this->assertContains('courses', $types);
        $this->assertContains('roles', $types);
        $this->assertContains('groups', $types);
        $this->assertContains('cohorts', $types);
        $this->assertContains('badges', $types);
        $this->assertContains('certificates', $types);

        foreach ($types as $type) {
            $provider = metadata_provider_factory::get_provider($type);
            $this->assertInstanceOf(metadata_provider::class, $provider);
            $this->assertEquals($type, $provider->get_type());
        }
    }

    /**
     * Test metadata_provider_factory throws for unknown type.
     */
    public function test_factory_unknown_type(): void {
        $this->expectException(\invalid_parameter_exception::class);
        metadata_provider_factory::get_provider('nonexistent');
    }
}
