"use strict";

/**
 * Gruntfile for local_mc_plugin
 *
 * This Gruntfile provides local build tasks for the plugin:
 * - AMD module minification (using terser)
 *
 * In CI, Moodle's grunt is run from the Moodle root directory,
 * so we don't need to load Moodle's core Gruntfile here.
 */
module.exports = function (grunt) {
    grunt.loadNpmTasks("grunt-terser");

    grunt.initConfig({
        terser: {
            options: {
                sourceMap: {
                    includeSources: true
                }
            },
            amd: {
                files: [{
                    expand: true,
                    cwd: "amd/src",
                    src: ["**/*.js"],
                    dest: "amd/build",
                    ext: ".min.js"
                }]
            }
        }
    });

    grunt.registerTask("default", ["terser:amd"]);
    grunt.registerTask("amd", "Minify AMD modules", ["terser:amd"]);

    // No-op stylelint task to satisfy Moodle's grunt when it finds CSS files
    grunt.registerTask("stylelint", "No-op stylelint (CSS is hand-written)", function () {
        grunt.log.ok("Skipping stylelint - styles.css is hand-written CSS");
    });
};
