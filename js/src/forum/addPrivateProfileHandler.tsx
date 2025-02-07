import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import app from 'flarum/forum/app';

export default function () {
  const style = document.createElement('style');
  style.textContent = `.UserPage .Button-badge { display: none; }`;
  document.body.appendChild(style);

  extend(UserPage.prototype, 'onupdate', function () {
    const isPrivateProfile = this.user?.attribute('privateProfile') === true;
    const isCurrentUser = app.session.user?.id() === this.user?.id();
    const isAdmin = app.session.user?.isAdmin();
    const isUnauthorizedViewer = isPrivateProfile && !isCurrentUser && !isAdmin;

    if (!isUnauthorizedViewer) {
      style.textContent = '';
    } else {
      style.textContent = `.UserPage .Button-badge { display: none; }`;

      const badgeElements = this.element.querySelectorAll('.Button-badge');
      badgeElements.forEach(badge => {
        badge.textContent = '';
      });

      const placeholderElement = this.element.querySelector('.PostsUserPage .Placeholder, .DiscussionsUserPage .Placeholder');

      if (placeholderElement?.querySelector('p')) {
        placeholderElement.querySelector('p').textContent = app.translator.trans('huseyinfiliz-private-profile.forum.profile_is_private');
      }
    }
  });
}
