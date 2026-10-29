import app from "flarum/admin/app";
import Extend from "flarum/common/extenders";
import PrivateProfilePage from "./components/PrivateProfilePage";

export default [
  new Extend.Admin().page(PrivateProfilePage).permission(
    () => ({
      icon: "fas fa-eye",
      label: app.translator.trans(
        "huseyinfiliz-private-profile.admin.permissions.bypass_profile_visibility",
      ),
      permission: "huseyinfiliz-private-profile.bypassProfileVisibility",
    }),
    "moderate",
  ),
];
