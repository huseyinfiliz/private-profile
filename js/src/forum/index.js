import app from "flarum/forum/app";
import addPrivateProfileSettings from "./addPrivateSettings";
import addPrivateProfileHandler from "./addPrivateProfileHandler";
import discoverProfileTabs from "./discoverProfileTabs";

app.initializers.add("private-profile", () => {
  addPrivateProfileSettings();
  addPrivateProfileHandler();
  discoverProfileTabs();
});
