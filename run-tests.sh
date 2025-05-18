#!/bin/bash

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Функция для отображения текста с цветом
print_colored() {
    echo -e "${2}${1}${NC}"
}

# Функция для отображения заголовка
print_header() {
    echo ""
    print_colored "============================================" $YELLOW
    print_colored " $1" $YELLOW
    print_colored "============================================" $YELLOW
    echo ""
}

# Функция для проверки успешности выполнения команды
check_result() {
    if [ $? -eq 0 ]; then
        print_colored "✓ $1 успешно выполнено" $GREEN
    else
        print_colored "✗ $1 завершилось с ошибкой" $RED
        exit 1
    fi
}

# Помощь по использованию скрипта
show_help() {
    echo "Использование: ./run-tests.sh [опция]"
    echo ""
    echo "Опции:"
    echo "  all       - запустить все тесты"
    echo "  unit      - запустить только модульные тесты"
    echo "  feature   - запустить только функциональные тесты"
    echo "  coverage  - сгенерировать отчет о покрытии кода"
    echo "  help      - показать эту помощь"
    echo ""
    exit 0
}

# Проверка наличия параметров
if [ $# -eq 0 ]; then
    show_help
fi

# Проверка установки зависимостей
if [ ! -d "vendor" ]; then
    print_header "Установка зависимостей"
    composer install
    check_result "Установка зависимостей"
fi

# Обработка параметров
case "$1" in
    all)
        print_header "Запуск всех тестов"
        ./vendor/bin/phpunit
        check_result "Запуск всех тестов"
        ;;
    unit)
        print_header "Запуск модульных тестов"
        ./vendor/bin/phpunit --testsuite Unit
        check_result "Запуск модульных тестов"
        ;;
    feature)
        print_header "Запуск функциональных тестов"
        ./vendor/bin/phpunit --testsuite Feature
        check_result "Запуск функциональных тестов"
        ;;
    coverage)
        print_header "Генерация отчета о покрытии кода"
        XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage
        check_result "Генерация отчета о покрытии кода"
        print_colored "Отчет о покрытии кода сохранен в директории 'coverage'" $GREEN
        ;;
    help)
        show_help
        ;;
    *)
        print_colored "Неизвестная опция: $1" $RED
        show_help
        ;;
esac

exit 0