Информация обновлена 21.8.2017

Проект - parsersPicksgrail (парсеры для сайта picksgrail.com)

В проекте используются такие библиотеки как:
- simple_html_dom (для парсинга информации с html)
- var_dumper(для удобной откладки)
- cloudflare-bypass(НЕЛЬЗЯ обновлять! Настроена исключительно 
под доску dev.bmbets.com)

В парсинге одной доски участвуют главные 3 файла:
1. "start..." - запускной файл, с которого все начинается. В нем
прописаны параметры исключительно для определенной доски.
2. "parser" - абстрактный класс, от которого наследуются все
классы досок.
3. Класс доски, которая находятся в директории "boards" определенной
доски.

Описание директорий в корне:
1. boards - директория с папками досок
2. cf-cookies - директория библиотеки cloudflare-bypass
3. helpers - директория с вспомогательными классами
4. vendor - директория библиотек, которые в большинстве подгружены
с помощью composer.

