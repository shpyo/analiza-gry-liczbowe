# Jak mądrzej wybierać liczby w grach liczbowych

Przewodnik oparty wyłącznie na matematyce i statystyce. Bez magii, bez "systemów gwarantujących wygraną". Tylko fakty.

---

## Najważniejsza rzecz, którą musisz wiedzieć

**Nie da się przewidzieć, jakie liczby padną w następnym losowaniu.**

To nie jest opinia — to matematyczny fakt. Każde losowanie jest niezależne od poprzednich. Maszyna losująca nie "pamięta" co wypadło tydzień temu. Jeśli ktoś twierdzi inaczej, to albo nie rozumie matematyki, albo chce ci coś sprzedać.

Ale — i to jest ważne "ale" — **możesz wybierać liczby mądrzej**. Nie chodzi o zwiększenie szansy wygranej (ta jest stała), ale o unikanie głupich błędów, które popełnia większość graczy.

---

## Gry — podstawowe informacje

| Gra | Ile skreślasz | Z ilu liczb | Szansa na główną wygraną |
|-----|--------------|-------------|--------------------------|
| **Lotto** | 6 | 49 | 1 do 13 983 816 |
| **Lotto Plus** | 6 | 49 (+ kula bonusowa) | 1 do 13 983 816 |
| **Mini Lotto** | 5 | 42 | 1 do 850 668 |
| **Multi Multi** | losowanych 20, zakład 1-10 | 80 | zależy od zakładu |

### Jak liczymy szansę?

Szansa na trafienie 6 z 49 to tak zwana **kombinacja** — ile jest sposobów wybrania 6 liczb z 49, gdy kolejność nie ma znaczenia.

Wzór: C(49, 6) = 49! / (6! × 43!) = **13 983 816**

Czyli masz 1 szansę na prawie 14 milionów. To jak trafić jedną konkretną sekundę w pół roku.

Dla Mini Lotto: C(42, 5) = **850 668** — dużo łatwiej, ale wciąż jak trafienie jednej sekundy w 10 dni.

**Ważne:** każda kombinacja 6 liczb z 49 ma DOKŁADNIE taką samą szansę — 1/13 983 816. Kupon 1-2-3-4-5-6 ma identyczną szansę jak 7-14-22-35-41-48. Matematyka nie robi wyjątków.

---

## Czym są metryki i po co je znać?

Metryki to mierzalne cechy kuponu — np. suma liczb, ile jest parzystych, jak bardzo są "rozrzucone". Nie zwiększają szansy wygranej, ale pomagają zrozumieć, jak wyglądają **typowe losowania** i unikać kuponów, które wyglądają dziwnie.

Dlaczego to ma sens? Bo jeśli w 7000 losowań Lotto **żadne** nie miało profilu "same niskie, same nieparzyste, zerowy rozstęp" — to choć taki kupon ma takie samo prawdopodobieństwo jak każdy inny, statystycznie jest ekstremalnie nietypowy.

---

## Metryka 1: Suma liczb

**Co to jest:** Dodajesz wszystkie skreślone liczby. Np. kupon 7 + 14 + 22 + 35 + 41 + 48 = **167**.

**Dlaczego warto patrzeć:** Suma losowanych liczb układa się w kształt dzwonu (rozkład normalny). Większość losowań daje sumę blisko środka, a ekstremalnie niskie lub wysokie sumy zdarzają się bardzo rzadko.

### Zakresy sum dla każdej gry

**Lotto (6 z 49):**

| Zakres | Od | Do | Jak często? |
|--------|----|----|-------------|
| Bardzo mała | 21 | 79 | ~5% losowań |
| Mała | 80 | 109 | ~20% losowań |
| **Średnia** | **110** | **170** | **~50% losowań** |
| Duża | 171 | 200 | ~20% losowań |
| Bardzo duża | 201 | 279 | ~5% losowań |

Średnia oczekiwana: 6 × (49+1) / 2 = **150**

**Mini Lotto (5 z 42):**

| Zakres | Od | Do |
|--------|----|----|
| Bardzo mała | 15 | 49 |
| Mała | 50 | 79 |
| **Średnia** | **80** | **120** |
| Duża | 121 | 159 |
| Bardzo duża | 160 | 200 |

Średnia oczekiwana: 5 × (42+1) / 2 = **107,5**

**Multi Multi (zakład na 10 z 80):**

Tutaj sprawa jest bardziej skomplikowana, bo losowanych jest 20 liczb, ale ty obstawiasz 1 do 10. Dla 10 liczb:
- Średnia: 10 × 81 / 2 = **405**
- Typowy zakres: **298–512**

**Wniosek:** Jeśli twój kupon Lotto ma sumę 35 albo 270, jest ekstremalnie nietypowy. Nie znaczy to, że nie może wygrać — ale historycznie takie sumy prawie nie padały.

---

## Metryka 2: Parzyste i nieparzyste

**Co to jest:** Ile z twoich liczb jest parzystych (2, 4, 6, 8...), a ile nieparzystych (1, 3, 5, 7...).

**Przykład:** Kupon 5, 12, 24, 31, 40, 47 → parzyste: 12, 24, 40 = **3 parzyste, 3 nieparzyste**

### Rozkład dla Lotto (6 z 49)

W puli 49 liczb jest 24 parzystych i 25 nieparzystych.

| Parzyste | Nieparzyste | Jak często? |
|----------|-------------|-------------|
| 0 | 6 | ~1,5% |
| 1 | 5 | ~10% |
| 2 | 4 | ~25% |
| **3** | **3** | **~31%** |
| 4 | 2 | ~22% |
| 5 | 1 | ~9% |
| 6 | 0 | ~1,5% |

**Wniosek:** Najczęściej wypada 2-4 parzystych. Kupon z samymi parzystymi albo samymi nieparzystymi to ~1,5% szansy. Nie jest to "niemożliwe", ale bardzo rzadkie.

### Dla Mini Lotto (5 z 42)

20 parzystych w puli 42. Najczęściej: 2-3 parzyste (~65% losowań).

### Dla Multi Multi (zakład 10 z 80)

40 parzystych w puli 80. Najczęściej: 4-6 parzystych w 10-liczbowym zakładzie.

---

## Metryka 3: Niskie i wysokie

**Co to jest:** Dzielimy pulę na pół — niskie i wysokie liczby.

| Gra | Niskie | Wysokie |
|-----|--------|---------|
| Lotto / Lotto Plus | 1–24 | 25–49 |
| Mini Lotto | 1–21 | 22–42 |
| Multi Multi | 1–40 | 41–80 |

**Dlaczego to ważne:** To działa dokładnie jak parzyste/nieparzyste. Kupon złożony wyłącznie z niskich albo wyłącznie z wysokich jest statystycznie nietypowy.

**Przykład:** Kupon 3, 7, 11, 15, 19, 23 — same niskie! Historycznie taki rozkład (6 niskich, 0 wysokich) zdarza się w ~1,5% losowań.

**Wniosek:** Celuj w mix 2-4 niskich (Lotto) lub 2-3 niskie (Mini Lotto).

---

## Metryka 4: Rozstęp (rozpiętość)

**Co to jest:** Różnica między największą a najmniejszą liczbą na kuponie.

**Przykład:** Kupon 7, 14, 22, 35, 41, 48 → rozstęp = 48 - 7 = **41**

### Typowe zakresy

**Lotto (6/49):**

| Rozstęp | Opis |
|---------|------|
| 0–19 | Bardzo mały — liczby blisko siebie |
| 20–29 | Mały |
| **30–39** | **Średni — najczęstszy** |
| 40–44 | Duży |
| 45–48 | Bardzo duży — prawie cała pula |

Oczekiwana wartość: (49+1) × 5 / 7 ≈ **35,7**

**Mini Lotto (5/42):**

| Rozstęp | Opis |
|---------|------|
| 0–12 | Bardzo mały |
| 13–22 | Mały |
| **23–31** | **Średni** |
| 32–37 | Duży |
| 38+ | Bardzo duży |

**Wniosek:** Kupon 21, 22, 23, 24, 25, 26 ma rozstęp 5 — ekstremalnie mały. Choć taka kombinacja ma identyczne prawdopodobieństwo, historycznie rozstępy poniżej 20 to zaledwie kilka procent losowań.

---

## Metryka 5: Dziesiątki (grupy po 10)

**Co to jest:** Ile różnych "dziesiątek" reprezentują twoje liczby.

Grupy: 1–10, 11–20, 21–30, 31–40, 41–49 (dla Lotto — 5 grup)

**Przykład:** Kupon 7, 14, 22, 35, 41, 48 → grupy: 1-10, 11-20, 21-30, 31-40, 41-49 = **5 różnych dziesiątek**

**Przykład zły:** Kupon 31, 33, 35, 37, 38, 39 → tylko 1 dziesiątka (31-40)

| Gra | Ile dziesiątek | Ile grup masz |
|-----|---------------|---------------|
| Lotto | 5 | najczęściej 4-5 |
| Mini Lotto | 5 (z mniejszą ostatnią) | najczęściej 4-5 |
| Multi Multi | 8 | najczęściej 6-8 |

**Wniosek:** Kupon rozłożony na 4-5 dziesiątek (Lotto) to typowy wzorzec. Jeśli wszystkie liczby są z jednej lub dwóch dziesiątek — to statystyczny dziwak.

---

## Metryka 6: Pary sąsiadów

**Co to jest:** Ile par liczb na kuponie różni się o 1 (np. 17 i 18, albo 33 i 34).

**Przykład:** Kupon 5, 17, 18, 24, 33, 34 → pary: (17,18) i (33,34) = **2 pary sąsiednich**

**Zaskakujący fakt:** Większość graczy intuicyjnie UNIKA par sąsiednich, myśląc że "nie wyglądają losowo". Tymczasem:

| Ile par | Jak często w Lotto? |
|---------|---------------------|
| 0 | ~35% |
| 1 | ~38% |
| 2 | ~20% |
| 3+ | ~7% |

**65% losowań Lotto zawiera co najmniej jedną parę sąsiednią!** Unikanie par to jeden z najczęstszych błędów graczy.

**Wniosek:** Nie bój się par sąsiednich. Jeśli twój kupon ma 0 lub 1 parę — jest typowy. Jeśli ma 3+ — jest nieco nietypowy, ale absolutnie możliwy.

---

## Metryka 7: Unikalne cyfry jedności

**Co to jest:** Ostatnia cyfra każdej liczby. Ile różnych masz?

**Przykład:** Kupon 11, 21, 31, 41, 43, 47 → cyfry jedności: 1, 1, 1, 1, 3, 7 = **3 unikalne**

**Przykład dobry:** Kupon 7, 14, 22, 35, 41, 48 → cyfry jedności: 7, 4, 2, 5, 1, 8 = **6 unikalnych**

W Lotto (6 liczb) najczęściej jest 5-6 różnych cyfr jedności. Kupon z 1-2 unikalnymi cyframi (np. same liczby kończące się na 3: 3, 13, 23, 33, 43) jest ekstremalnie nietypowy.

**Wniosek:** Więcej różnych cyfr jedności = bardziej "rozrzucony" kupon. 5-6 unikalnych jest idealne dla Lotto.

---

## Profil strukturalny — jak to wszystko połączyć

System łączy kilka metryk w jeden "odcisk palca" kuponu, np.:

**3e3o_3l3h_sM_c0_rL** — co to znaczy?

- **3e3o** = 3 parzyste, 3 nieparzyste
- **3l3h** = 3 niskie, 3 wysokie
- **sM** = suma średnia (110–170)
- **c0** = 0 par sąsiednich
- **rL** = rozstęp duży (40–44)

To jest "typowy" profil — pojawia się w kilku procentach losowań. Profile ekstremalnie nietypowe (np. 0 parzystych, 6 niskich, suma XS, rozstęp XS) nigdy nie wystąpiły w historii.

**Wniosek praktyczny:** Nie musisz liczyć tego ręcznie. Ale jeśli twój kupon wygląda "podejrzanie równo" (np. same niskie parzyste liczby blisko siebie) — jest strukturalnie nietypowy.

---

## Wielkie pytanie: Czy statystyka pomoże wygrać?

### Krótka odpowiedź: NIE.

### Dłuższa odpowiedź:

**Czego statystyka NIE może zrobić:**
- Przewidzieć, jakie liczby padną
- Znaleźć "gorących" liczb, które będą dalej padać
- Znaleźć "zaległych" liczb, które "muszą" w końcu wypaść
- Odkryć "wzorce" w losowaniach (bo ich nie ma)

**Dlaczego nie?** Bo loteria jest losowa. Każde losowanie jest NIEZALEŻNE od poprzednich. Kula numer 7 nie "wie", że wypadła 3 razy z rzędu. Kula numer 42 nie "wie", że nie wypadła od 30 losowań.

To się nazywa **błąd hazardzisty** (gambler's fallacy) — przekonanie, że jeśli coś się działo przez jakiś czas, to zmieni się w przyszłości. Moneta, która wyrzuciła 10 orłów z rzędu, wciąż ma 50% szansy na orła w 11. rzucie.

### Czego statystyka MOŻE ci pomóc uniknąć:

1. **Nietypowych kombinacji** — filtrowanie po sumie, parzystości, rozstępie eliminuje ~10-20% kombinacji, które nigdy historycznie nie padły. Nie dlatego, że mają mniejsze prawdopodobieństwo, ale dlatego, że są strukturalnie skrajne.

2. **Popularnych kombinacji** — to jest PRAWDZIWA wartość dodana, o której mało kto mówi. W loterii pula wygranych jest dzielona między wszystkich zwycięzców. Jeśli 10 000 osób skreśli 1-2-3-4-5-6 i ta kombinacja wygra — każdy dostanie 1/10 000 puli. Jeśli ty skreślisz coś mniej oczywistego i wygrasz sam — dostajesz całość.

   **Unikaj:**
   - Sekwencji (1-2-3-4-5-6)
   - Dat urodzin (same liczby 1-31)
   - Wzorów na kuponie (przekątne, krzyże)
   - "Szczęśliwych" liczb (7, 13, 21, 33 — wybieranych przez miliony osób)

3. **Błędów poznawczych** — wiesz teraz, że pary sąsiednie są normalne, że suma powinna być blisko środka, że potrzebujesz mixu parzystych/nieparzystych i niskich/wysokich.

---

## Praktyczny "przepis" na kupon

Nie gwarantuje wygranej (nic nie gwarantuje), ale daje matematycznie rozsądny kupon:

### Lotto / Lotto Plus (6 z 49)

1. Suma liczb: **110–170** (średni zakres)
2. Parzyste: **2–4** (nie same parzyste, nie same nieparzyste)
3. Niskie (1-24): **2–4** (mix niskich i wysokich)
4. Rozstęp: **30–44** (liczby rozłożone, nie skupione)
5. Dziesiątki: **4–5 różnych** grup
6. Pary sąsiednie: **0–2** (nie bój się jednej pary!)
7. Cyfry jedności: **5–6 różnych**

### Mini Lotto (5 z 42)

1. Suma liczb: **80–120**
2. Parzyste: **2–3**
3. Niskie (1-21): **2–3**
4. Rozstęp: **23–37**
5. Dziesiątki: **4–5 różnych**
6. Pary sąsiednie: **0–1**

### Multi Multi (zakład 10 z 80)

1. Suma: **298–512** (dla 10 liczb)
2. Parzyste: **4–6**
3. Niskie (1-40): **4–6**
4. Rozstęp: duży (liczby rozłożone po całej puli)
5. Dziesiątki: **6–8 różnych** grup
6. Pary sąsiednie: **1–3** (przy 10 liczbach to normalne)

---

## Czy "gorące" i "zimne" liczby mają sens?

**"Gorąca liczba"** = taka, która ostatnio padała częściej niż średnia.
**"Zimna liczba"** = taka, która ostatnio padała rzadziej niż średnia.

**Czy to pomaga?** Nie. To są naturalne wahania losowe. Jeśli rzucisz monetą 100 razy, nie dostaniesz dokładnie 50 orłów i 50 reszek — może być 55/45 albo 42/58. To nie znaczy, że moneta jest "gorąca" na orły.

W loterii jest tak samo. W oknie 500 losowań Lotto, każda liczba powinna wypaść średnio ~61 razy (500 × 6/49). Ale naturalne odchylenie to ±11 — więc jeśli liczba 7 wypadła 72 razy, a liczba 33 wypadła 50 razy, to jest zupełnie normalne i nie mówi NIC o przyszłości.

**Jedyny wyjątek:** Gdyby loteria NIE była uczciwa (np. wadliwa maszyna), historyczne częstości mogłyby na to wskazywać. Ale polskie loterie są auditowane i kontrolowane. Zakładaj, że są uczciwe.

---

## Podsumowanie

| Co | Prawda czy mit? |
|----|-----------------|
| "Gorące liczby częściej padają" | MIT — przeszłość nie wpływa na przyszłość |
| "Zaległa liczba musi w końcu wypaść" | MIT — błąd hazardzisty |
| "Pewne wzorce można przewidzieć" | MIT — losowość nie ma wzorców |
| "Warto unikać ekstremalnych kuponów" | PRAWDA — nie zwiększa szansy, ale eliminuje strukturalne dziwactwa |
| "Warto unikać popularnych kombinacji" | PRAWDA — w razie wygranej nie dzielisz puli z tysiącami |
| "Pary sąsiednie nie padają" | MIT — 65% losowań ma co najmniej jedną parę |
| "Filtrowanie po sumie/parzystości pomaga" | CZĘŚCIOWO — eliminuje nietypowe profile, ale nie zwiększa P(wygrana) |

**Ostatnie słowo:** Loteria to rozrywka, nie inwestycja. Matematycznie, wartość oczekiwana kuponu Lotto (6 zł) to około 2,50 zł — czyli średnio TRACISZ na każdym kuponie. Graj dla zabawy, nie wydawaj pieniędzy, których nie możesz stracić. A jeśli już grasz — niech twój kupon przynajmniej wygląda jak typowe losowanie, a nie jak ciąg danych z kalendarza.
