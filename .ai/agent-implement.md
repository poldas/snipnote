aktualnie na stronie logowania, rejestracji i przypomnienia hasła jest nadmiarowy js, który nie powinien tam
  być, tak samo jak na widoku publicznym notatki, przygotuj osobne layouty dla strony głównej razem ze
  stronami autoryzacji, layout dashboardu i layout do notatki publicznej (pamiętaj o rodzajach notatki jak
  todo, która ma funkcjonalność js do obsługi listy todo), każdy z layoutów powinien ładować jedynie te js i
  css które potrzebuje (js do obsługi edycji notatki nie jest potrzebny na auth i public note), zapoznaj się z
  następującymi plikami kontekstowymi

  ### prd:
  @.ai/spec/prd.md

  ### tech stack:
  @.ai/spec/tech-stack.md

  ### programming rules
  @.cursor/rules/programming-rules.md
  @.ai/spec/ui-colors.md

  ### architecture
  @.ai/spec/ui-plan.md
  @.ai/spec/api-plan.md
  @README.md

  Twoim zadaniem jest zachowanie wszystkich aktualnych funkcjonalności i architektury strony, a dostosowanie
  pozbycie się ładowania zbędnych plików i ich ujawniania (np na main page widzę kod js z dashboardu).
  Najpierw przygotuj szczegółowy plan implementacji analizując codebase, zwróć uwagę na proces rejestracji i
  potwierdzania maila, wcześniej był problem z sesją i tokenami csrf. Po przygotowaniu planu zaimplementuj
  zgodnie z najlepszymi zasadami programowania. Zawsze pisz kod zgodny semantycznie z tym co robi aplikacja,
  nie dopisuj bezmyślnie nowych rzeczy, bo pasują.
