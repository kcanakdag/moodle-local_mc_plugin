module.exports = {
  "amd/src/**/*.js": () => [
    "npx grunt amd",
    "git add amd/build/"
  ]
};
