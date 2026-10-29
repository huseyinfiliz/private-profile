import app from "flarum/forum/app";
import addPrivateProfileSettings from "./addPrivateSettings";
import addPrivateProfileHandler from "./addPrivateProfileHandler";

app.initializers.add("private-profile", () => {
  addPrivateProfileSettings();
  addPrivateProfileHandler();
});
