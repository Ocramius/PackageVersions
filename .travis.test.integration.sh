set -x

PROJECT_DIR=${PWD}

echo "Project dir: ${PROJECT_DIR}"

DIR="/tmp/test-integration/$TRAVIS_BUILD_NUMBER";
mkdir -p $DIR
cd $DIR
rm -f *
cp ${PROJECT_DIR}/test/integration/composer.json .

composer config repositories.from-source path ${PROJECT_DIR}
composer install
composer install --no-dev
