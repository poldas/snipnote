<conversation_summary>
<decisions>
1. Wszystkie typy notatek są równorzędne w MVP.
2. URL notatki i katalogu użytkownika ma być losowym UUID.
3. Współedytor ma takie same uprawnienia jak właściciel, z wyjątkiem usuwania notatki.
4. Współedytor może dodawać, edytować i usuwać labele oraz zarządzać zaproszeniami do notatki (także usunąć siebie).
5. Zaproszenie działa dopiero po rejestracji użytkownika z tym samym adresem e-mail, ale nie jest nigdzie wysyłane mailem.
6. Nie ma procesu akceptacji zaproszenia — dostęp działa automatycznie po zalogowaniu.
7. Labele są lokalne dla notatki i obsługują pełny Unicode bez emotek, OR w wyszukiwaniu, z możliwością filtrowania przez `label:`.
8. Wyszukiwanie odbywa się w jednym polu po tytule, opisie i opcjonalnie po labelach.
9. Dane widoczne w widoku publicznym: tytuł, opis, labele, data utworzenia.
10. Współedytor może zmieniać widoczność notatki i generować nowy URL.
11. Nowy URL unieważnia poprzedni natychmiast.
12. Notatka prywatna widoczna tylko dla właściciela i współedytorów, niezalogowany nie ma dostępu.
13. Po usunięciu swojego dostępu współedytor zostaje natychmiast przekierowany na dashboard.
14. Usunięcie notatki usuwa wszystkie powiązania (label, udostępnienia, URL).
15. Widok publiczny katalogu pokazuje wyłącznie publiczne notatki, z paginacją.
16. W widoku publicznym nie wyświetla się autor, dane takie same jak dla zalogowanego.
17. Sortowanie wyłącznie po dacie (od najnowszych).
18. Logowanie wyłącznie email/hasło, bez weryfikacji email.
19. Zapisywanie zmian tylko przyciskiem „Zapisz”, brak auto-save.
20. Obsługa błędów w prostych toastach (bez dedykowanych widoków).
21. W MVP brak wymagań dotyczących metryk sukcesu i brak limitów współedytorów/labeli (poza długościami).
</decisions>

<matched_recommendations>
1. Rezygnacja z live-preview na rzecz przycisku „Podgląd”, w celu skrócenia implementacji.
2. Brak walidacji unikalności tytułów, aby uprościć MVP.
3. Utrzymanie wspólnego mechanizmu wyszukiwania dla użytkowników publicznych i zalogowanych.
4. Odrzucenie weryfikacji e-mail i rezygnacja z rozbudowy profili — redukcja kosztu MVP.
5. Natychmiastowe unieważnianie poprzednich URL po wygenerowaniu nowego dla bezpieczeństwa i prostoty.
6. Pozostawienie publiczności jako wyłącznie parametr widoczności, nie uprawnień.
7. Zachowanie pełnego copy-access dla publicznych treści, bez ograniczeń.
8. Skrócenie obsługi błędów do toastów UI, bez stron 403/404 dedykowanych.
</matched_recommendations>

<prd_planning_summary>
Głównym celem MVP jest umożliwienie tworzenia, udostępniania i wspólnej edycji notatek (np. przepisy, checklisty, kod, artykuły) z możliwością publicznego dostępu poprzez unikalny URL.

**Kluczowe wymagania funkcjonalne:**
- Dodawanie notatek z tytułem, opisem (markdown), labelami.
- Widoczność notatek: prywatna/publiczna z możliwością zmiany.
- Generowanie losowego URL notatki, z możliwością regeneracji.
- Udostępnianie notatek po e-mailu, bez akceptacji zaproszenia, dostęp po zalogowaniu.
- Współedytor posiada prawie pełne uprawnienia (bez usuwania notatki).
- Wyszukiwanie po tytule, opisie i labelach (OR, `label:`).
- Publiczny katalog notatek użytkownika wyłącznie z notatkami publicznymi, z paginacją.
- Markdown z przyciskiem „Podgląd”.
- Proste UX: toast przy błędach, brak stron błędów.

**Zasada projektowania z uwzględnieniem DDD**
- Logika biznesowa powinna być realizowana w warstwie domenowej, niezależnej od szczegółów frameworka czy infrastruktury.
- Spójność danych notatek (treści, widoczności, współdzielenia, URL, labeli) powinna być utrzymywana w ramach jasno określonego modelu domenowego.
- Konkretne granice agregatów oraz modułów domenowych zostaną zaprojektowane na późniejszym etapie (projekt techniczny), przy zachowaniu zasad separacji logiki biznesowej od warstwy transportowej (np. kontrolerów).

**Kluczowe ścieżki użytkownika:**
- Tworzenie i edycja notatki → zapis → generowanie URL → opcjonalne ustawienie jako publiczna → udostępnienie e-mail.
- Współedytor loguje się → otwiera link → automatycznie uzyskuje dostęp → może edytować i zarządzać udostępnieniami.
- Użytkownik publiczny → wchodzi w katalog przez URL → przegląda publiczne notatki → wchodzi w notatkę, czyta, kopiuje treść.

**Kryteria sukcesu MVP (akceptacja funkcjonalna):**
- Wszystkie role poprawnie widzą/edycją w granicach uprawnień.
- Współedytorzy mają pełny dostęp oprócz usuwania notatki.
- Notatki prywatne są niewidoczne publicznie nawet z prawidłowym URL.
- URL poprawnie unieważnia poprzednie po wygenerowaniu nowego.
- Wyszukiwanie działa wspólnie dla tytułów, opisów i labeli (OR).
- UI umożliwia pełną obsługę MVP bez dokumentacji, z jasnymi komunikatami toast.

</prd_planning_summary>
</conversation_summary>
