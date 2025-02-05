import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import app from 'flarum/forum/app';

export default function () {
  extend(UserPage.prototype, 'onupdate', function () {
    if (!this.user || this.user.attribute('privateProfile') !== true) return;

    const isCurrentUser = app.session.user?.id() === this.user.id();
    const isAdmin = app.session.user?.isAdmin();
    if (isCurrentUser || isAdmin) return;

    const placeholderElement =
      this.element.querySelector('.PostsUserPage .Placeholder') ||
      this.element.querySelector('.DiscussionsUserPage .Placeholder');
    if (placeholderElement) {
      const pElement = placeholderElement.querySelector('p');
      if (pElement) {
        pElement.textContent = app.translator.trans('huseyinfiliz-private-profile.forum.profile_is_private');
        pElement.classList.add('private-profile-message');
      }
    }

    const dropdownMenu = this.element.querySelector('.Dropdown-menu.dropdown-menu');
    if (dropdownMenu) {
      dropdownMenu.querySelectorAll('.Button-badge').forEach(badge => {
        badge.textContent = '';
      });
    }
  });
}
