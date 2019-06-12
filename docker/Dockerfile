# This dockerfile takes the current users uid/gid at build time and adjusts reality
# so that the running user for www-data is actually the same as the launching user.
FROM uofa/s2i-shepherd-drupal

ARG USER_ID
ARG GROUP_ID

# Need to switch from www-data to root to do the change of uid
USER 0:0

# Remove existing www user (both) and group (dialout is the users group on mac).
RUN \
if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    userdel -f www-data \
    && groupdel dialout \
    && if getent group www-data ; then groupdel www-data; fi \
    && groupadd -g ${GROUP_ID} www-data \
    && useradd -l -u ${USER_ID} -g www-data www-data \
    && install -d -m 0755 -o www-data -g www-data /home/www-data \
    && chown --changes --no-dereference --recursive \
        --from=33 ${USER_ID}:${GROUP_ID} \
        /var/www \
        /run/lock \
        /var/run/apache2 \
        /var/log/apache2 \
        /var/lock/apache2 \
        /code \
        /shared; \
fi

# Add the chromedriver repo using php, no wget or curl yet.
RUN php -n -r 'echo file_get_contents("https://dl-ssl.google.com/linux/linux_signing_key.pub");' | apt-key add - \
&& echo 'deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main' > /etc/apt/sources.list.d/google-chrome.list

# Upgrade all currently installed packages and install additional packages.
RUN apt-get update \
&& apt-get -y install php-sqlite3 php-xdebug php7.2-cli git wget sudo unzip libnotify-bin google-chrome-stable vim \
&& sed -ri 's/^zend.assertions\s*=\s*-1/zend.assertions = 1/g' /etc/php/7.2/cli/php.ini \
&& sed -i 's/^\(allow_url_fopen\s*=\s*\).*$/\1on/g' /etc/php/7.2/mods-available/php_custom.ini \
&& CHROME_DRIVER_VERSION=$(php -n -r 'echo file_get_contents("https://chromedriver.storage.googleapis.com/LATEST_RELEASE");') \
&& wget https://chromedriver.storage.googleapis.com/$CHROME_DRIVER_VERSION/chromedriver_linux64.zip \
&& unzip -o chromedriver_linux64.zip -d /usr/local/bin \
&& rm -f chromedriver_linux64.zip \
&& chmod +x /usr/local/bin/chromedriver \
&& apt-get -y autoremove && apt-get -y autoclean && apt-get clean && rm -rf /var/lib/apt/lists /tmp/* /var/tmp/*

# Install Composer.
RUN wget -q https://getcomposer.org/installer -O - | php -d allow_url_fopen=On -- --install-dir=/usr/local/bin --filename=composer

COPY ./docker/xdebug.ini /etc/php/7.2/mods-available/xdebug.ini

RUN echo "www-data ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/www-data

# Enable xdebug.
RUN phpenmod -v ALL -s ALL xdebug

USER www-data
