import { IKnowledgeBase } from './types';

export const K5_KNOWLEDGE_BASE: IKnowledgeBase = {
  'Algoritmusok': {
    meta: {
      id: 'k5-algoritmusok',
      title: 'Algoritmusok',
      description: 'Alapvető algoritmusok és logikai gondolkodás.',
      localization: {
        de: { title: 'Algorithmen', description: 'Grundlegende Algorithmen und logisches Denken.' },
        ro: { title: 'Algoritmi', description: 'Algoritmi de bază și gândire logică.' },
        en: { title: 'Algorithms', description: 'Basic algorithms and logical thinking.' }
      }
    },
    quiz: {
      multipleChoice: [
        {
          question: 'Mi az algoritmus?',
          options: {
            correct: 'Lépések sorozata egy feladat megoldására.',
            incorrect: ['Egy számítógépes program neve.', 'Egyfajta csokoládé.', 'Egy híres feltaláló.']
          },
          explanation: 'Az algoritmus egyértelmű utasítások sorozata, amely egy adott probléma megoldásához vezet.',
          localization: {
            de: {
              question: 'Was ist ein Algorithmus?',
              options: {
                correct: 'Eine Reihe von Schritten zur Lösung einer Aufgabe.',
                incorrect: ['Der Name eines Computerprogramms.', 'Eine Schokoladensorte.', 'Ein berühmter Erfinder.']
              },
              explanation: 'Ein Algorithmus ist eine eindeutige Folge von Anweisungen, die zur Lösung eines bestimmten Problems führt.'
            },
            ro: {
              question: 'Ce este un algoritm?',
              options: {
                correct: 'O serie de pași pentru a rezolva o sarcină.',
                incorrect: ['Numele unui program de calculator.', 'Un tip de ciocolată.', 'Un inventator celebru.']
              },
              explanation: 'Un algoritm este o secvență clară de instrucțiuni care duce la rezolvarea unei probleme specifice.'
            },
            en: {
              question: 'What is an algorithm?',
              options: {
                correct: 'A series of steps to solve a task.',
                incorrect: ['The name of a computer program.', 'A type of chocolate.', 'A famous inventor.']
              },
              explanation: 'An algorithm is a clear sequence of instructions that leads to the solution of a specific problem.'
            }
          }
        },
        {
          question: 'Melyik NEM jellemzője egy jó algoritmusnak?',
          options: {
            correct: 'Végtelen',
            incorrect: ['Egyértelmű', 'Véges', 'Hatékony']
          },
          explanation: 'Egy algoritmusnak mindig véges számú lépésből kell állnia, hogy befejeződjön.',
          localization: {
            de: {
              question: 'Welches ist KEIN Merkmal eines guten Algorithmus?',
              options: {
                correct: 'Unendlich',
                incorrect: ['Eindeutig', 'Endlich', 'Effizient']
              },
              explanation: 'Ein Algorithmus muss immer aus einer endlichen Anzahl von Schritten bestehen, um abgeschlossen zu werden.'
            },
            ro: {
              question: 'Care NU este o caracteristică a unui algoritm bun?',
              options: {
                correct: 'Infinit',
                incorrect: ['Clar', 'Finit', 'Eficient']
              },
              explanation: 'Un algoritm trebuie să constea întotdeauna dintr-un număr finit de pași pentru a fi finalizat.'
            },
            en: {
              question: 'Which is NOT a characteristic of a good algorithm?',
              options: {
                correct: 'Infinite',
                incorrect: ['Unambiguous', 'Finite', 'Efficient']
              },
              explanation: 'An algorithm must always consist of a finite number of steps to be completed.'
            }
          }
        },
        {
          question: 'Hogyan nevezzük az algoritmusok leírására használt, egyszerűsített, emberi nyelven írt kódot?',
          options: {
            correct: 'Pszeudokód',
            incorrect: ['Hieroglifa', 'Forráskód', 'Titkosírás']
          },
          explanation: 'A pszeudokód segít megtervezni az algoritmust, mielőtt azt egy konkrét programozási nyelven megírnánk.',
          localization: {
            de: {
              question: 'Wie nennt man den vereinfachten, in menschlicher Sprache geschriebenen Code, der zur Beschreibung von Algorithmen verwendet wird?',
              options: {
                correct: 'Pseudocode',
                incorrect: ['Hieroglyphe', 'Quellcode', 'Geheimschrift']
              },
              explanation: 'Pseudocode hilft, den Algorithmus zu entwerfen, bevor er in einer bestimmten Programmiersprache geschrieben wird.'
            },
            ro: {
              question: 'Cum se numește codul simplificat, scris în limbaj uman, folosit pentru a descrie algoritmi?',
              options: {
                correct: 'Pseudocod',
                incorrect: ['Hieroglifă', 'Cod sursă', 'Cifru']
              },
              explanation: 'Pseudocodul ajută la proiectarea algoritmului înainte de a-l scrie într-un limbaj de programare specific.'
            },
            en: {
              question: 'What do we call the simplified, human-readable code used to describe algorithms?',
              options: {
                correct: 'Pseudocode',
                incorrect: ['Hieroglyph', 'Source code', 'Cipher']
              },
              explanation: 'Pseudocode helps in designing the algorithm before writing it in a specific programming language.'
            }
          }
        },
        {
          question: 'Mi a "ciklus" egy algoritmusban?',
          options: {
            correct: 'Egy utasítássorozat ismétlése.',
            incorrect: ['Az algoritmus vége.', 'Egy hiba a kódban.', 'Egy matematikai művelet.']
          },
          explanation: 'A ciklusok lehetővé teszik, hogy bizonyos lépéseket többször is végrehajtsunk, amíg egy feltétel teljesül.',
          localization: {
            de: {
              question: 'Was ist eine "Schleife" in einem Algorithmus?',
              options: {
                correct: 'Die Wiederholung einer Anweisungsfolge.',
                incorrect: ['Das Ende des Algorithmus.', 'Ein Fehler im Code.', 'Eine mathematische Operation.']
              },
              explanation: 'Schleifen ermöglichen es, bestimmte Schritte mehrmals auszuführen, bis eine Bedingung erfüllt ist.'
            },
            ro: {
              question: 'Ce este un "ciclu" într-un algoritm?',
              options: {
                correct: 'Repetarea unei secvențe de instrucțiuni.',
                incorrect: ['Sfârșitul algoritmului.', 'O eroare în cod.', 'O operație matematică.']
              },
              explanation: 'Ciclurile permit executarea anumitor pași de mai multe ori până când o condiție este îndeplinită.'
            },
            en: {
              question: 'What is a "loop" in an algorithm?',
              options: {
                correct: 'Repeating a sequence of instructions.',
                incorrect: ['The end of the algorithm.', 'An error in the code.', 'A mathematical operation.']
              },
              explanation: 'Loops allow certain steps to be executed multiple times until a condition is met.'
            }
          }
        },
        {
            question: 'Melyik a legismertebb példa a sorba rendező algoritmusra a mindennapi életben?',
            options: {
                correct: 'Kártyapakli sorba rendezése.',
                incorrect: ['Teafőzés.', 'Útvonaltervezés a boltba.', 'Bekapcsolni a TV-t.']
            },
            explanation: 'A kártyák sorba rendezése egy klasszikus példa, ahol egy algoritmust (pl. buborékrendezés vagy beillesztéses rendezés) alkalmazunk a lapok sorrendbe tételére.',
            localization: {
                de: {
                    question: 'Was ist das bekannteste Beispiel für einen Sortieralgorithmus im Alltag?',
                    options: {
                        correct: 'Ein Kartenspiel sortieren.',
                        incorrect: ['Tee kochen.', 'Den Weg zum Geschäft planen.', 'Den Fernseher einschalten.']
                    },
                    explanation: 'Das Sortieren von Karten ist ein klassisches Beispiel, bei dem ein Algorithmus (z. B. Bubblesort oder Insertionsort) verwendet wird, um die Karten in eine Reihenfolge zu bringen.'
                },
                ro: {
                    question: 'Care este cel mai cunoscut exemplu de algoritm de sortare în viața de zi cu zi?',
                    options: {
                        correct: 'Sortarea unui pachet de cărți.',
                        incorrect: ['Prepararea ceaiului.', 'Planificarea traseului către magazin.', 'Pornirea televizorului.']
                    },
                    explanation: 'Sortarea cărților este un exemplu clasic în care se folosește un algoritm (de ex. sortare cu bule sau sortare prin inserție) pentru a aranja cărțile într-o anumită ordine.'
                },
                en: {
                    question: 'What is the most well-known example of a sorting algorithm in everyday life?',
                    options: {
                        correct: 'Sorting a deck of cards.',
                        incorrect: ['Making tea.', 'Planning a route to the store.', 'Turning on the TV.']
                    },
                    explanation: 'Sorting cards is a classic example where an algorithm (e.g., bubble sort or insertion sort) is used to put the cards in order.'
                }
            }
        },
        {
            question: 'Mit jelent az "elágazás" egy algoritmusban?',
            options: {
                correct: 'Feltételtől függően más-más lépést hajt végre.',
                incorrect: ['Az algoritmus két részre bontása.', 'Egy hurok, ami sosem áll le.', 'Az adatok tárolása.']
            },
            explanation: 'Az elágazás (vagy feltételes utasítás) lehetővé teszi, hogy az algoritmus különböző utakat válasszon egy feltétel igaz vagy hamis voltától függően. Például: "HA esik az eső, AKKOR vigyél esernyőt, KÜLÖNBEN ne."',
            localization: {
                de: {
                    question: 'Was bedeutet "Verzweigung" in einem Algorithmus?',
                    options: {
                        correct: 'Abhängig von einer Bedingung einen anderen Schritt ausführen.',
                        incorrect: ['Den Algorithmus in zwei Teile teilen.', 'Eine Schleife, die niemals endet.', 'Daten speichern.']
                    },
                    explanation: 'Eine Verzweigung (oder bedingte Anweisung) ermöglicht es dem Algorithmus, je nachdem, ob eine Bedingung wahr oder falsch ist, unterschiedliche Wege zu wählen. Zum Beispiel: "WENN es regnet, DANN nimm einen Regenschirm, SONST nicht."'
                },
                ro: {
                    question: 'Ce înseamnă "ramificare" într-un algoritm?',
                    options: {
                        correct: 'Execută un pas diferit în funcție de o condiție.',
                        incorrect: ['Împărțirea algoritmului în două părți.', 'O buclă care nu se termină niciodată.', 'Stocarea datelor.']
                    },
                    explanation: 'Ramificarea (sau instrucțiunea condiționată) permite algoritmului să aleagă căi diferite în funcție de dacă o condiție este adevărată sau falsă. De exemplu: "DACĂ plouă, ATUNCI ia o umbrelă, ALTFEL nu."'
                },
                en: {
                    question: 'What does "branching" mean in an algorithm?',
                    options: {
                        correct: 'Executing a different step depending on a condition.',
                        incorrect: ['Splitting the algorithm into two parts.', 'A loop that never ends.', 'Storing data.']
                    },
                    explanation: 'Branching (or a conditional statement) allows the algorithm to choose different paths depending on whether a condition is true or false. For example: "IF it is raining, THEN take an umbrella, ELSE do not."'
                }
            }
        },
        {
            question: 'Melyik grafikus eszköz segít leginkább egy algoritmus lépéseinek megjelenítésében?',
            options: {
                correct: 'Folyamatábra',
                incorrect: ['Fénykép', 'Térkép', 'Hangfájl']
            },
            explanation: 'A folyamatábra különböző alakzatokat (téglalap, rombusz, ovális) használ az algoritmus lépéseinek, döntéseinek és folyamatának vizuális ábrázolására.',
            localization: {
                de: {
                    question: 'Welches grafische Werkzeug hilft am besten bei der Darstellung der Schritte eines Algorithmus?',
                    options: {
                        correct: 'Flussdiagramm',
                        incorrect: ['Foto', 'Karte', 'Audiodatei']
                    },
                    explanation: 'Ein Flussdiagramm verwendet verschiedene Formen (Rechteck, Raute, Oval), um die Schritte, Entscheidungen und den Ablauf eines Algorithmus visuell darzustellen.'
                },
                ro: {
                    question: 'Ce instrument grafic ajută cel mai mult la vizualizarea pașilor unui algoritm?',
                    options: {
                        correct: 'Diagramă de flux',
                        incorrect: ['Fotografie', 'Hartă', 'Fișier audio']
                    },
                    explanation: 'O diagramă de flux folosește diferite forme (dreptunghi, romb, oval) pentru a reprezenta vizual pașii, deciziile și fluxul unui algoritm.'
                },
                en: {
                    question: 'Which graphical tool is most helpful for visualizing the steps of an algorithm?',
                    options: {
                        correct: 'Flowchart',
                        incorrect: ['Photograph', 'Map', 'Audio file']
                    },
                    explanation: 'A flowchart uses different shapes (rectangle, diamond, oval) to visually represent the steps, decisions, and flow of an algorithm.'
                }
            }
        },
        {
            question: 'Ha egy robotnak azt az utasítást adod, hogy "Menj előre, amíg falat nem érsz", ez egy példa a ...',
            options: {
                correct: 'Ciklusra',
                incorrect: ['Elágazásra', 'Szekvenciára', 'Bemenetre']
            },
            explanation: 'Ez egy ciklus, mert a "menj előre" művelet ismétlődik, amíg a feltétel (falat érsz) nem teljesül.',
            localization: {
                de: {
                    question: 'Wenn du einem Roboter den Befehl gibst "Gehe vorwärts, bis du auf eine Wand triffst", ist das ein Beispiel für ...',
                    options: {
                        correct: 'eine Schleife',
                        incorrect: ['eine Verzweigung', 'eine Sequenz', 'eine Eingabe']
                    },
                    explanation: 'Dies ist eine Schleife, da die Aktion "vorwärts gehen" wiederholt wird, bis die Bedingung (auf eine Wand treffen) erfüllt ist.'
                },
                ro: {
                    question: 'Dacă îi dai unui robot instrucțiunea "Mergi înainte până când atingi un perete", acesta este un exemplu de...',
                    options: {
                        correct: 'Ciclu',
                        incorrect: ['Ramificare', 'Secvență', 'Intrare']
                    },
                    explanation: 'Acesta este un ciclu, deoarece acțiunea "mergi înainte" se repetă până când condiția (atingerea unui perete) este îndeplinită.'
                },
                en: {
                    question: 'If you give a robot the instruction "Move forward until you hit a wall", this is an example of a...',
                    options: {
                        correct: 'Loop',
                        incorrect: ['Branch', 'Sequence', 'Input']
                    },
                    explanation: 'This is a loop because the "move forward" action is repeated until the condition (hitting a wall) is met.'
                }
            }
        },
        {
            question: 'Mi a "bemenet" (input) egy algoritmusban?',
            options: {
                correct: 'Az adatok, amikkel az algoritmus dolgozik.',
                incorrect: ['Az algoritmus végeredménye.', 'Az algoritmus lépéseinek száma.', 'Az algoritmus írójának neve.']
            },
            explanation: 'A bemenet az az információ, amit az algoritmus felhasznál a feladat elvégzéséhez. Például egy összeadó algoritmus bemenete a két összeadandó szám.',
            localization: {
                de: {
                    question: 'Was ist die "Eingabe" (Input) in einem Algorithmus?',
                    options: {
                        correct: 'Die Daten, mit denen der Algorithmus arbeitet.',
                        incorrect: ['Das Endergebnis des Algorithmus.', 'Die Anzahl der Schritte im Algorithmus.', 'Der Name des Autors des Algorithmus.']
                    },
                    explanation: 'Die Eingabe sind die Informationen, die der Algorithmus zur Erledigung der Aufgabe verwendet. Zum Beispiel sind die Eingaben für einen Additionsalgorithmus die beiden zu addierenden Zahlen.'
                },
                ro: {
                    question: 'Ce este "intrare" (input) într-un algoritm?',
                    options: {
                        correct: 'Datele cu care lucrează algoritmul.',
                        incorrect: ['Rezultatul final al algoritmului.', 'Numărul de pași din algoritm.', 'Numele autorului algoritmului.']
                    },
                    explanation: 'Intrarea este informația pe care algoritmul o folosește pentru a îndeplini sarcina. De exemplu, intrarea pentru un algoritm de adunare sunt cele două numere care trebuie adunate.'
                },
                en: {
                    question: 'What is the "input" in an algorithm?',
                    options: {
                        correct: 'The data the algorithm works with.',
                        incorrect: ['The final result of the algorithm.', 'The number of steps in the algorithm.', 'The name of the algorithm's author.']
                    },
                    explanation: 'The input is the information that the algorithm uses to perform its task. For example, the input for an addition algorithm is the two numbers to be added.'
                }
            }
        },
        {
            question: 'Mi a "kimenet" (output) egy algoritmusban?',
            options: {
                correct: 'Az algoritmus által előállított eredmény.',
                incorrect: ['Az első lépés az algoritmusban.', 'A probléma leírása.', 'Az adatok, amikkel az algoritmus dolgozik.']
            },
            explanation: 'A kimenet az algoritmus futásának végeredménye, a megoldás, amit a bemeneti adatok alapján előállított.',
            localization: {
                de: {
                    question: 'Was ist die "Ausgabe" (Output) in einem Algorithmus?',
                    options: {
                        correct: 'Das vom Algorithmus erzeugte Ergebnis.',
                        incorrect: ['Der erste Schritt im Algorithmus.', 'Die Beschreibung des Problems.', 'Die Daten, mit denen der Algorithmus arbeitet.']
                    },
                    explanation: 'Die Ausgabe ist das Endergebnis der Ausführung des Algorithmus, die Lösung, die er basierend auf den Eingabedaten erzeugt hat.'
                },
                ro: {
                    question: 'Ce este "ieșire" (output) într-un algoritm?',
                    options: {
                        correct: 'Rezultatul produs de algoritm.',
                        incorrect: ['Primul pas în algoritm.', 'Descrierea problemei.', 'Datele cu care lucrează algoritmul.']
                    },
                    explanation: 'Ieșirea este rezultatul final al rulării algoritmului, soluția pe care a produs-o pe baza datelor de intrare.'
                },
                en: {
                    question: 'What is the "output" in an algorithm?',
                    options: {
                        correct: 'The result produced by the algorithm.',
                        incorrect: ['The first step in the algorithm.', 'The description of the problem.', 'The data the algorithm works with.']
                    },
                    explanation: 'The output is the final result of running the algorithm, the solution it has produced based on the input data.'
                }
            }
        },
        {
            question: 'Melyik algoritmus a leghatékonyabb egy telefonkönyvben egy név megkeresésére?',
            options: {
                correct: 'Bináris keresés',
                incorrect: ['Lineáris keresés', 'Buborékrendezés', 'Véletlenszerű keresés']
            },
            explanation: 'Mivel a telefonkönyv rendezett, a bináris keresés a leghatékonyabb. Mindig a lista közepén ellenőriz, és eldönti, melyik felében folytassa a keresést, így gyorsan leszűkíti a lehetőségeket.',
            localization: {
                de: {
                    question: 'Welcher Algorithmus ist am effizientesten, um einen Namen in einem Telefonbuch zu finden?',
                    options: {
                        correct: 'Binäre Suche',
                        incorrect: ['Lineare Suche', 'Bubblesort', 'Zufällige Suche']
                    },
                    explanation: 'Da das Telefonbuch sortiert ist, ist die binäre Suche am effizientesten. Sie prüft immer die Mitte der Liste und entscheidet, in welcher Hälfte sie die Suche fortsetzt, wodurch die Möglichkeiten schnell eingegrenzt werden.'
                },
                ro: {
                    question: 'Ce algoritm este cel mai eficient pentru a găsi un nume într-o carte de telefon?',
                    options: {
                        correct: 'Căutare binară',
                        incorrect: ['Căutare liniară', 'Sortare cu bule', 'Căutare aleatorie']
                    },
                    explanation: 'Deoarece cartea de telefon este sortată, căutarea binară este cea mai eficientă. Verifică întotdeauna mijlocul listei și decide în ce jumătate să continue căutarea, reducând rapid opțiunile.'
                },
                en: {
                    question: 'Which algorithm is most efficient for finding a name in a phone book?',
                    options: {
                        correct: 'Binary search',
                        incorrect: ['Linear search', 'Bubble sort', 'Random search']
                    },
                    explanation: 'Since the phone book is sorted, binary search is the most efficient. It always checks the middle of the list and decides which half to continue searching in, quickly narrowing down the possibilities.'
                }
            }
        },
        {
            question: 'Mit csinál a "buborékrendezés" algoritmus?',
            options: {
                correct: 'Sorba rendezi az elemeket páronkénti cserével.',
                incorrect: ['Megkeresi a legnagyobb elemet.', 'Megszámolja az elemeket.', 'Kitörli az elemeket.']
            },
            explanation: 'A buborékrendezés többször végigmegy a listán, összehasonlítja a szomszédos elemeket, és felcseréli őket, ha rossz sorrendben vannak. A nagyobb elemek így lassan "felbuborékolnak" a lista végére.',
            localization: {
                de: {
                    question: 'Was macht der "Bubblesort"-Algorithmus?',
                    options: {
                        correct: 'Sortiert Elemente durch paarweisen Austausch.',
                        incorrect: ['Findet das größte Element.', 'Zählt die Elemente.', 'Löscht die Elemente.']
                    },
                    explanation: 'Bubblesort durchläuft die Liste mehrmals, vergleicht benachbarte Elemente und tauscht sie aus, wenn sie in der falschen Reihenfolge sind. Die größeren Elemente "blubbern" so langsam an das Ende der Liste.'
                },
                ro: {
                    question: 'Ce face algoritmul "sortare cu bule"?',
                    options: {
                        correct: 'Sortează elementele prin schimburi în perechi.',
                        incorrect: ['Găsește cel mai mare element.', 'Numără elementele.', 'Șterge elementele.']
                    },
                    explanation: 'Sortarea cu bule parcurge lista de mai multe ori, compară elementele adiacente și le schimbă dacă sunt în ordinea greșită. Elementele mai mari "urcă" astfel încet spre sfârșitul listei.'
                },
                en: {
                    question: 'What does the "bubble sort" algorithm do?',
                    options: {
                        correct: 'Sorts elements by swapping them in pairs.',
                        incorrect: ['Finds the largest element.', 'Counts the elements.', 'Deletes the elements.']
                    },
                    explanation: 'Bubble sort repeatedly steps through the list, compares adjacent elements and swaps them if they are in the wrong order. The larger elements thus slowly "bubble" to the end of the list.'
                }
            }
        },
        {
            question: 'Egy recept az ebéd elkészítéséhez tekinthető ...',
            options: {
                correct: 'algoritmusnak.',
                incorrect: ['programozási nyelvnek.', 'hardvernek.', 'szoftvernek.']
            },
            explanation: 'A recept lépésről lépésre leírja, hogyan kell eljutni a hozzávalóktól (bemenet) a kész ételig (kimenet), ami pontosan megfelel egy algoritmus definíciójának.',
            localization: {
                de: {
                    question: 'Ein Rezept zum Kochen des Mittagessens kann als ... betrachtet werden.',
                    options: {
                        correct: 'ein Algorithmus.',
                        incorrect: ['eine Programmiersprache.', 'Hardware.', 'Software.']
                    },
                    explanation: 'Ein Rezept beschreibt Schritt für Schritt, wie man von den Zutaten (Eingabe) zum fertigen Gericht (Ausgabe) gelangt, was genau der Definition eines Algorithmus entspricht.'
                },
                ro: {
                    question: 'O rețetă pentru prepararea prânzului poate fi considerată...',
                    options: {
                        correct: 'un algoritm.',
                        incorrect: ['un limbaj de programare.', 'hardware.', 'software.']
                    },
                    explanation: 'Rețeta descrie pas cu pas cum se ajunge de la ingrediente (intrare) la mâncarea finală (ieșire), ceea ce corespunde exact definiției unui algoritm.'
                },
                en: {
                    question: 'A recipe for cooking lunch can be considered...',
                    options: {
                        correct: 'an algorithm.',
                        incorrect: ['a programming language.', 'hardware.', 'software.']
                    },
                    explanation: 'A recipe describes step-by-step how to get from the ingredients (input) to the finished dish (output), which exactly matches the definition of an algorithm.'
                }
            }
        },
        {
            question: 'Mi a "szekvencia" egy algoritmusban?',
            options: {
                correct: 'Az utasítások végrehajtásának sorrendje.',
                incorrect: ['Egy hibaüzenet.', 'Egy ismétlődő rész.', 'Egy feltétel.']
            },
            explanation: 'A szekvencia az algoritmus alapvető építőköve, ami azt jelenti, hogy az utasítások egymás után, meghatározott sorrendben hajtódnak végre.',
            localization: {
                de: {
                    question: 'Was ist eine "Sequenz" in einem Algorithmus?',
                    options: {
                        correct: 'Die Reihenfolge, in der Anweisungen ausgeführt werden.',
                        incorrect: ['Eine Fehlermeldung.', 'Ein sich wiederholender Teil.', 'Eine Bedingung.']
                    },
                    explanation: 'Die Sequenz ist der grundlegende Baustein eines Algorithmus, was bedeutet, dass die Anweisungen nacheinander in einer bestimmten Reihenfolge ausgeführt werden.'
                },
                ro: {
                    question: 'Ce este o "secvență" într-un algoritm?',
                    options: {
                        correct: 'Ordinea în care sunt executate instrucțiunile.',
                        incorrect: ['Un mesaj de eroare.', 'O parte care se repetă.', 'O condiție.']
                    },
                    explanation: 'Secvența este elementul de bază al unui algoritm, ceea ce înseamnă că instrucțiunile sunt executate una după alta, într-o ordine specifică.'
                },
                en: {
                    question: 'What is a "sequence" in an algorithm?',
                    options: {
                        correct: 'The order in which instructions are executed.',
                        incorrect: ['An error message.', 'A repeating part.', 'A condition.']
                    },
                    explanation: 'A sequence is the basic building block of an algorithm, meaning that instructions are executed one after another in a specific order.'
                }
            }
        },
        {
            question: 'Ha egy algoritmus nem ad mindig helyes eredményt, akkor az...',
            options: {
                correct: 'hibás.',
                incorrect: ['hatékony.', 'gyors.', 'végtelen.']
            },
            explanation: 'Egy algoritmus legfontosabb tulajdonsága a helyesség. Ha nem ad megbízhatóan jó eredményt, akkor az algoritmus hibás, és javításra szorul.',
            localization: {
                de: {
                    question: 'Wenn ein Algorithmus nicht immer das richtige Ergebnis liefert, dann ist er...',
                    options: {
                        correct: 'fehlerhaft.',
                        incorrect: ['effizient.', 'schnell.', 'unendlich.']
                    },
                    explanation: 'Die wichtigste Eigenschaft eines Algorithmus ist die Korrektheit. Wenn er nicht zuverlässig gute Ergebnisse liefert, ist der Algorithmus fehlerhaft und muss korrigiert werden.'
                },
                ro: {
                    question: 'Dacă un algoritm nu dă întotdeauna rezultatul corect, atunci este...',
                    options: {
                        correct: 'incorect.',
                        incorrect: ['eficient.', 'rapid.', 'infinit.']
                    },
                    explanation: 'Cea mai importantă proprietate a unui algoritm este corectitudinea. Dacă nu oferă în mod fiabil rezultate bune, atunci algoritmul este incorect și trebuie corectat.'
                },
                en: {
                    question: 'If an algorithm does not always give the correct result, then it is...',
                    options: {
                        correct: 'incorrect.',
                        incorrect: ['efficient.', 'fast.', 'infinite.']
                    },
                    explanation: 'The most important property of an algorithm is correctness. If it does not reliably produce good results, then the algorithm is incorrect and needs to be fixed.'
                }
            }
        },
        {
            question: 'Melyik NEM egy algoritmus alapvető építőeleme?',
            options: {
                correct: 'Véletlen',
                incorrect: ['Szekvencia', 'Elágazás', 'Ciklus']
            },
            explanation: 'Bár a véletlenszerűség használható algoritmusokban, az alapvető, strukturált programozáshoz szükséges építőelemek a szekvencia (lépések sorban), az elágazás (döntés) és a ciklus (ismétlés).',
            localization: {
                de: {
                    question: 'Was ist KEIN grundlegender Baustein eines Algorithmus?',
                    options: {
                        correct: 'Zufall',
                        incorrect: ['Sequenz', 'Verzweigung', 'Schleife']
                    },
                    explanation: 'Obwohl Zufälligkeit in Algorithmen verwendet werden kann, sind die grundlegenden Bausteine, die für die strukturierte Programmierung benötigt werden, die Sequenz (Schritte in einer Reihe), die Verzweigung (Entscheidung) und die Schleife (Wiederholung).'
                },
                ro: {
                    question: 'Care NU este un element de bază al unui algoritm?',
                    options: {
                        correct: 'Aleatoriu',
                        incorrect: ['Secvență', 'Ramificare', 'Ciclu']
                    },
                    explanation: 'Deși aleatoritatea poate fi folosită în algoritmi, elementele de bază necesare pentru programarea structurată sunt secvența (pași în ordine), ramificarea (decizie) și ciclul (repetare).'
                },
                en: {
                    question: 'Which is NOT a fundamental building block of an algorithm?',
                    options: {
                        correct: 'Randomness',
                        incorrect: ['Sequence', 'Branching', 'Loop']
                    },
                    explanation: 'Although randomness can be used in algorithms, the fundamental building blocks needed for structured programming are sequence (steps in order), branching (decision), and loop (repetition).'
                }
            }
        },
        {
            question: 'Mi a célja egy algoritmus hatékonyságának vizsgálatának?',
            options: {
                correct: 'Megtudni, mennyi időt és memóriát használ.',
                incorrect: ['Megtudni, ki írta.', 'Megváltoztatni a színét.', 'Lefordítani más nyelvre.']
            },
            explanation: 'A hatékonyság azt méri, hogy egy algoritmus milyen gyorsan fut (időbonyolultság) és mennyi erőforrást, például memóriát használ (tárbonyolultság).',
            localization: {
                de: {
                    question: 'Was ist der Zweck der Effizienzanalyse eines Algorithmus?',
                    options: {
                        correct: 'Herausfinden, wie viel Zeit und Speicher er verbraucht.',
                        incorrect: ['Herausfinden, wer ihn geschrieben hat.', 'Seine Farbe ändern.', 'Ihn in eine andere Sprache übersetzen.']
                    },
                    explanation: 'Effizienz misst, wie schnell ein Algorithmus läuft (Zeitkomplexität) und wie viele Ressourcen, wie z.B. Speicher, er verbraucht (Speicherkomplexität).'
                },
                ro: {
                    question: 'Care este scopul analizei eficienței unui algoritm?',
                    options: {
                        correct: 'Să afli cât timp și memorie folosește.',
                        incorrect: ['Să afli cine l-a scris.', 'Să-i schimbi culoarea.', 'Să-l traduci în altă limbă.']
                    },
                    explanation: 'Eficiența măsoară cât de repede rulează un algoritm (complexitatea timpului) și câte resurse, cum ar fi memoria, folosește (complexitatea spațiului).'
                },
                en: {
                    question: 'What is the purpose of analyzing an algorithm's efficiency?',
                    options: {
                        correct: 'To find out how much time and memory it uses.',
                        incorrect: ['To find out who wrote it.', 'To change its color.', 'To translate it into another language.']
                    },
                    explanation: 'Efficiency measures how fast an algorithm runs (time complexity) and how many resources, such as memory, it uses (space complexity).'
                }
            }
        },
        {
            question: 'Ha egy algoritmus minden lehetséges bemenetre leáll véges időn belül, akkor az...',
            options: {
                correct: 'véges.',
                incorrect: ['helytelen.', 'hatástalan.', 'pszeudokód.']
            },
            explanation: 'A végesség az algoritmusok egyik alapvető kritériuma. Garantálnia kell, hogy nem fut a végtelenségig, hanem befejezi a működését.',
            localization: {
                de: {
                    question: 'Wenn ein Algorithmus für jede mögliche Eingabe in endlicher Zeit anhält, dann ist er...',
                    options: {
                        correct: 'endlich.',
                        incorrect: ['falsch.', 'ineffektiv.', 'Pseudocode.']
                    },
                    explanation: 'Die Endlichkeit ist eines der grundlegenden Kriterien für Algorithmen. Er muss garantieren, dass er nicht unendlich lange läuft, sondern seine Ausführung beendet.'
                },
                ro: {
                    question: 'Dacă un algoritm se oprește într-un timp finit pentru orice intrare posibilă, atunci este...',
                    options: {
                        correct: 'finit.',
                        incorrect: ['incorect.', 'ineficient.', 'pseudocod.']
                    },
                    explanation: 'Finitudinea este unul dintre criteriile de bază ale algoritmilor. Acesta trebuie să garanteze că nu va rula la infinit, ci își va încheia execuția.'
                },
                en: {
                    question: 'If an algorithm stops in a finite time for every possible input, then it is...',
                    options: {
                        correct: 'finite.',
                        incorrect: ['incorrect.', 'inefficient.', 'pseudocode.']
                    },
                    explanation: 'Finiteness is one of the basic criteria for algorithms. It must guarantee that it will not run forever but will complete its execution.'
                }
            }
        },
        {
            question: 'Mit jelent a "hibakeresés" (debugging) egy algoritmus kapcsán?',
            options: {
                correct: 'A hibák megkeresése és kijavítása.',
                incorrect: ['Az algoritmus felgyorsítása.', 'Az algoritmus lerajzolása.', 'Új funkciók hozzáadása.']
            },
            explanation: 'A hibakeresés az a folyamat, amely során azonosítjuk, megtaláljuk és kijavítjuk a hibákat (bug-okat) egy algoritmusban vagy programban, hogy az megfelelően működjön.',
            localization: {
                de: {
                    question: 'Was bedeutet "Debugging" im Zusammenhang mit einem Algorithmus?',
                    options: {
                        correct: 'Fehler finden und beheben.',
                        incorrect: ['Den Algorithmus beschleunigen.', 'Den Algorithmus zeichnen.', 'Neue Funktionen hinzufügen.']
                    },
                    explanation: 'Debugging ist der Prozess, bei dem Fehler (Bugs) in einem Algorithmus oder Programm identifiziert, gefunden und behoben werden, damit es ordnungsgemäß funktioniert.'
                },
                ro: {
                    question: 'Ce înseamnă "depanare" (debugging) în legătură cu un algoritm?',
                    options: {
                        correct: 'Găsirea și corectarea erorilor.',
                        incorrect: ['Accelerarea algoritmului.', 'Desenarea algoritmului.', 'Adăugarea de noi funcționalități.']
                    },
                    explanation: 'Depanarea este procesul de identificare, găsire și corectare a erorilor (bug-urilor) într-un algoritm sau program pentru ca acesta să funcționeze corect.'
                },
                en: {
                    question: 'What does "debugging" mean in the context of an algorithm?',
                    options: {
                        correct: 'Finding and fixing errors.',
                        incorrect: ['Speeding up the algorithm.', 'Drawing the algorithm.', 'Adding new features.']
                    },
                    explanation: 'Debugging is the process of identifying, finding, and fixing errors (bugs) in an algorithm or program so that it functions correctly.'
                }
            }
        },
        {
            question: 'Melyik az az algoritmus, amely egy lista minden elemét sorban végignézi, amíg meg nem találja a keresett elemet?',
            options: {
                correct: 'Lineáris keresés',
                incorrect: ['Bináris keresés', 'Gyorsrendezés', 'Ugró keresés']
            },
            explanation: 'A lineáris vagy szekvenciális keresés a legegyszerűbb keresési módszer: az elejétől kezdve egyesével ellenőrzi az elemeket.',
            localization: {
                de: {
                    question: 'Welcher Algorithmus überprüft jedes Element einer Liste der Reihe nach, bis er das gesuchte Element findet?',
                    options: {
                        correct: 'Lineare Suche',
                        incorrect: ['Binäre Suche', 'Quicksort', 'Jump Search']
                    },
                    explanation: 'Die lineare oder sequentielle Suche ist die einfachste Suchmethode: Sie überprüft die Elemente nacheinander, beginnend am Anfang.'
                },
                ro: {
                    question: 'Care este algoritmul care parcurge fiecare element al unei liste în ordine până când găsește elementul căutat?',
                    options: {
                        correct: 'Căutare liniară',
                        incorrect: ['Căutare binară', 'Sortare rapidă', 'Căutare prin salturi']
                    },
                    explanation: 'Căutarea liniară sau secvențială este cea mai simplă metodă de căutare: verifică elementele unul câte unul, începând de la început.'
                },
                en: {
                    question: 'Which algorithm checks each element of a list in order until it finds the element it is looking for?',
                    options: {
                        correct: 'Linear search',
                        incorrect: ['Binary search', 'Quicksort', 'Jump search']
                    },
                    explanation: 'Linear or sequential search is the simplest search method: it checks the elements one by one, starting from the beginning.'
                }
            }
        },
        {
            question: 'Amikor a GPS megtervezi a legrövidebb utat A-ból B-be, mit használ?',
            options: {
                correct: 'Egy útvonalkereső algoritmust.',
                incorrect: ['Egy véletlenszám-generátort.', 'Egy szövegszerkesztőt.', 'Egy zenelejátszót.']
            },
            explanation: 'A GPS-ek komplex útvonalkereső algoritmusokat (pl. Dijkstra-algoritmus) használnak, hogy megtalálják a legjobb útvonalat a térképen lévő pontok között.',
            localization: {
                de: {
                    question: 'Wenn das GPS die kürzeste Route von A nach B plant, was verwendet es?',
                    options: {
                        correct: 'Einen Routenfindungsalgorithmus.',
                        incorrect: ['Einen Zufallszahlengenerator.', 'Einen Texteditor.', 'Einen Musikplayer.']
                    },
                    explanation: 'GPS-Geräte verwenden komplexe Routenfindungsalgorithmen (z. B. Dijkstra-Algorithmus), um den besten Weg zwischen Punkten auf einer Karte zu finden.'
                },
                ro: {
                    question: 'Când un GPS planifică cel mai scurt traseu de la A la B, ce folosește?',
                    options: {
                        correct: 'Un algoritm de găsire a rutei.',
                        incorrect: ['Un generator de numere aleatorii.', 'Un editor de text.', 'Un player muzical.']
                    },
                    explanation: 'GPS-urile folosesc algoritmi complecși de găsire a rutei (de ex. algoritmul lui Dijkstra) pentru a găsi cel mai bun traseu între punctele de pe o hartă.'
                },
                en: {
                    question: 'When a GPS plans the shortest route from A to B, what does it use?',
                    options: {
                        correct: 'A route-finding algorithm.',
                        incorrect: ['A random number generator.', 'A text editor.', 'A music player.']
                    },
                    explanation: 'GPS devices use complex route-finding algorithms (e.g., Dijkstra's algorithm) to find the best path between points on a map.'
                }
            }
        },
        {
            question: 'A logikai gondolkodás...?',
            options: {
                correct: 'kulcsfontosságú az algoritmusok készítéséhez.',
                incorrect: ['csak a matematikában fontos.', 'nem szükséges a programozáshoz.', 'ugyanaz, mint a rajzolás.']
            },
            explanation: 'Az algoritmusok lényegében a logika és a strukturált gondolkodás megtestesülései. Képesnek kell lennünk a problémát logikai lépésekre bontani.',
            localization: {
                de: {
                    question: 'Logisches Denken ist...?',
                    options: {
                        correct: 'entscheidend für die Erstellung von Algorithmen.',
                        incorrect: ['nur in der Mathematik wichtig.', 'nicht notwendig für das Programmieren.', 'dasselbe wie Zeichnen.']
                    },
                    explanation: 'Algorithmen sind im Wesentlichen die Verkörperung von Logik und strukturiertem Denken. Man muss in der Lage sein, ein Problem in logische Schritte zu zerlegen.'
                },
                ro: {
                    question: 'Gândirea logică este...?',
                    options: {
                        correct: 'crucială pentru crearea algoritmilor.',
                        incorrect: ['importantă doar în matematică.', 'ne-necesară pentru programare.', 'același lucru cu desenul.']
                    },
                    explanation: 'Algoritmii sunt, în esență, întruchiparea logicii și a gândirii structurate. Trebuie să fim capabili să descompunem o problemă în pași logici.'
                },
                en: {
                    question: 'Logical thinking is...?',
                    options: {
                        correct: 'crucial for creating algorithms.',
                        incorrect: ['only important in mathematics.', 'not necessary for programming.', 'the same as drawing.']
                    },
                    explanation: 'Algorithms are essentially the embodiment of logic and structured thinking. One must be able to break down a problem into logical steps.'
                }
            }
        },
        {
            question: 'Mi a különbség egy algoritmus és egy program között?',
            options: {
                correct: 'Az algoritmus a terv, a program a megvalósítás.',
                incorrect: ['Nincs különbség.', 'A program gyorsabb.', 'Az algoritmusnak mindig van felhasználói felülete.']
            },
            explanation: 'Az algoritmus egy elvont ötlet, a lépések leírása. A program pedig ennek az ötletnek a konkrét megvalósítása egy adott programozási nyelven, amit a számítógép végre tud hajtani.',
            localization: {
                de: {
                    question: 'Was ist der Unterschied zwischen einem Algorithmus und einem Programm?',
                    options: {
                        correct: 'Der Algorithmus ist der Plan, das Programm die Umsetzung.',
                        incorrect: ['Es gibt keinen Unterschied.', 'Ein Programm ist schneller.', 'Ein Algorithmus hat immer eine Benutzeroberfläche.']
                    },
                    explanation: 'Der Algorithmus ist eine abstrakte Idee, die Beschreibung der Schritte. Das Programm ist die konkrete Umsetzung dieser Idee in einer bestimmten Programmiersprache, die der Computer ausführen kann.'
                },
                ro: {
                    question: 'Care este diferența dintre un algoritm și un program?',
                    options: {
                        correct: 'Algoritmul este planul, programul este implementarea.',
                        incorrect: ['Nu există nicio diferență.', 'Un program este mai rapid.', 'Un algoritm are întotdeauna o interfață de utilizator.']
                    },
                    explanation: 'Algoritmul este o idee abstractă, descrierea pașilor. Programul este implementarea concretă a acestei idei într-un limbaj de programare specific, pe care computerul îl poate executa.'
                },
                en: {
                    question: 'What is the difference between an algorithm and a program?',
                    options: {
                        correct: 'The algorithm is the plan, the program is the implementation.',
                        incorrect: ['There is no difference.', 'A program is faster.', 'An algorithm always has a user interface.']
                    },
                    explanation: 'The algorithm is an abstract idea, the description of the steps. The program is the concrete implementation of this idea in a specific programming language that the computer can execute.'
                }
            }
        },
        {
            question: 'Egy "lépj egyet előre, majd fordulj jobbra" utasítássorozat egy példa a ...',
            options: {
                correct: 'Szekvenciára',
                incorrect: ['Ciklusra', 'Elágazásra', 'Kimenetre']
            },
            explanation: 'Ez egy szekvencia, mert az utasítások egymás után, egy meghatározott sorrendben hajtódnak végre, feltételek vagy ismétlés nélkül.',
            localization: {
                de: {
                    question: 'Eine Anweisungsfolge "gehe einen Schritt vorwärts, dann drehe dich nach rechts" ist ein Beispiel für ...',
                    options: {
                        correct: 'eine Sequenz',
                        incorrect: ['eine Schleife', 'eine Verzweigung', 'eine Ausgabe']
                    },
                    explanation: 'Dies ist eine Sequenz, da die Anweisungen nacheinander in einer bestimmten Reihenfolge ohne Bedingungen oder Wiederholungen ausgeführt werden.'
                },
                ro: {
                    question: 'O secvență de instrucțiuni "fă un pas înainte, apoi întoarce-te la dreapta" este un exemplu de...',
                    options: {
                        correct: 'Secvență',
                        incorrect: ['Ciclu', 'Ramificare', 'Ieșire']
                    },
                    explanation: 'Aceasta este o secvență, deoarece instrucțiunile sunt executate una după alta, într-o ordine specifică, fără condiții sau repetări.'
                },
                en: {
                    question: 'An instruction sequence "take one step forward, then turn right" is an example of a...',
                    options: {
                        correct: 'Sequence',
                        incorrect: ['Loop', 'Branch', 'Output']
                    },
                    explanation: 'This is a sequence because the instructions are executed one after another in a specific order, without conditions or repetition.'
                }
            }
        },
        {
            question: 'Az algoritmusoknak ... kell lenniük.',
            options: {
                correct: 'egyértelműnek és végrehajthatónak',
                incorrect: ['hosszúnak és bonyolultnak', 'titkosnak és érthetetlennek', 'mindig vizuálisnak']
            },
            explanation: 'Egy algoritmus minden lépésének pontosan meghatározottnak és megvalósíthatónak kell lennie, nem adhat teret a félreértésnek.',
            localization: {
                de: {
                    question: 'Algorithmen müssen ... sein.',
                    options: {
                        correct: 'eindeutig und ausführbar',
                        incorrect: ['lang und kompliziert', 'geheim und unverständlich', 'immer visuell']
                    },
                    explanation: 'Jeder Schritt eines Algorithmus muss genau definiert und ausführbar sein und darf keinen Raum für Missverständnisse lassen.'
                },
                ro: {
                    question: 'Algoritmii trebuie să fie...',
                    options: {
                        correct: 'clari și executabili',
                        incorrect: ['lungi și complicați', 'secreți și de neînțeles', 'întotdeauna vizuali']
                    },
                    explanation: 'Fiecare pas al unui algoritm trebuie să fie precis definit și executabil, fără a lăsa loc de interpretări greșite.'
                },
                en: {
                    question: 'Algorithms must be...',
                    options: {
                        correct: 'unambiguous and executable',
                        incorrect: ['long and complicated', 'secret and incomprehensible', 'always visual']
                    },
                    explanation: 'Every step of an algorithm must be precisely defined and executable, leaving no room for misunderstanding.'
                }
            }
        }
      ],
      typing: [
        {
          question: 'Hogyan nevezzük az algoritmus lépéseit ábrázoló grafikus diagramot? (egy szó)',
          answer: ['folyamatábra'],
          explanation: 'A folyamatábra szabványos szimbólumokkal jeleníti meg az algoritmus folyamatát.',
          localization: {
            de: {
              question: 'Wie nennt man das grafische Diagramm, das die Schritte eines Algorithmus darstellt? (ein Wort)',
              answer: ['Flussdiagramm'],
              explanation: 'Ein Flussdiagramm stellt den Ablauf des Algorithmus mit standardisierten Symbolen dar.'
            },
            ro: {
              question: 'Cum se numește diagrama grafică ce reprezintă pașii unui algoritm? (un cuvânt)',
              answer: ['diagramadeflux'],
              explanation: 'Diagrama de flux afișează fluxul algoritmului folosind simboluri standardizate.'
            },
            en: {
              question: 'What is the graphical diagram representing the steps of an algorithm called? (one word)',
              answer: ['flowchart'],
              explanation: 'A flowchart displays the flow of the algorithm using standardized symbols.'
            }
          }
        },
        {
          question: 'Mi a neve annak a lépéssorozatnak, ami megold egy problémát?',
          answer: ['algoritmus'],
          explanation: 'Az algoritmus egy recepthez hasonló, lépésről-lépésre vezető útmutató.',
          localization: {
            de: {
              question: 'Wie nennt man die Abfolge von Schritten, die ein Problem löst?',
              answer: ['Algorithmus'],
              explanation: 'Ein Algorithmus ist wie ein Rezept, eine Schritt-für-Schritt-Anleitung.'
            },
            ro: {
              question: 'Care este numele seriei de pași care rezolvă o problemă?',
              answer: ['algoritm'],
              explanation: 'Un algoritm este ca o rețetă, un ghid pas cu pas.'
            },
            en: {
              question: 'What is the name for the series of steps that solves a problem?',
              answer: ['algorithm'],
              explanation: 'An algorithm is like a recipe, a step-by-step guide.'
            }
          }
        },
        {
            question: 'Hogyan nevezzük azt az eljárást, amikor egy algoritmusban hibát keresünk és javítunk ki?',
            answer: ['hibakeresés', 'debuggolás'],
            explanation: 'A hibakeresés (debugging) elengedhetetlen része a programozásnak és algoritmusfejlesztésnek.',
            localization: {
                de: {
                    question: 'Wie nennt man den Prozess des Findens und Behebens von Fehlern in einem Algorithmus?',
                    answer: ['Debugging'],
                    explanation: 'Debugging ist ein wesentlicher Bestandteil der Programmierung und Algorithmenentwicklung.'
                },
                ro: {
                    question: 'Cum se numește procesul de găsire și corectare a erorilor într-un algoritm?',
                    answer: ['depanare'],
                    explanation: 'Depanarea (debugging) este o parte esențială a programării și dezvoltării algoritmilor.'
                },
                en: {
                    question: 'What is the process of finding and fixing errors in an algorithm called?',
                    answer: ['debugging'],
                    explanation: 'Debugging is an essential part of programming and algorithm development.'
                }
            }
        },
        {
            question: 'Melyik alapvető építőelem ismétel meg egy utasítássorozatot? (egy szó)',
            answer: ['ciklus'],
            explanation: 'A ciklus (vagy hurok) addig ismétel egy vagy több lépést, amíg egy megadott feltétel teljesül.',
            localization: {
                de: {
                    question: 'Welcher grundlegende Baustein wiederholt eine Folge von Anweisungen? (ein Wort)',
                    answer: ['Schleife'],
                    explanation: 'Eine Schleife wiederholt einen oder mehrere Schritte, bis eine bestimmte Bedingung erfüllt ist.'
                },
                ro: {
                    question: 'Ce element de bază repetă o secvență de instrucțiuni? (un cuvânt)',
                    answer: ['ciclu'],
                    explanation: 'Ciclul (sau bucla) repetă unul sau mai mulți pași până când o anumită condiție este îndeplinită.'
                },
                en: {
                    question: 'Which fundamental building block repeats a sequence of instructions? (one word)',
                    answer: ['loop'],
                    explanation: 'A loop repeats one or more steps until a specified condition is met.'
                }
            }
        },
        {
            question: 'Mi a neve a rendezett listában való gyors keresésnek, ami mindig a lista közepét nézi?',
            answer: ['bináris keresés'],
            explanation: 'A bináris keresés megfelezi a keresési területet minden lépésben, ezért nagyon hatékony.',
            localization: {
                de: {
                    question: 'Wie heißt die schnelle Suche in einer sortierten Liste, die immer die Mitte der Liste prüft?',
                    answer: ['binäre Suche'],
                    explanation: 'Die binäre Suche halbiert den Suchbereich bei jedem Schritt und ist daher sehr effizient.'
                },
                ro: {
                    question: 'Cum se numește căutarea rapidă într-o listă sortată care verifică mereu mijlocul listei?',
                    answer: ['căutare binară'],
                    explanation: 'Căutarea binară înjumătățește spațiul de căutare la fiecare pas, fiind astfel foarte eficientă.'
                },
                en: {
                    question: 'What is the name of the fast search in a sorted list that always checks the middle of the list?',
                    answer: ['binary search'],
                    explanation: 'Binary search halves the search space with each step, making it very efficient.'
                }
            }
        },
        {
            question: 'Mi az algoritmus által felhasznált adat? (egy szó)',
            answer: ['bemenet'],
            explanation: 'A bemenet (input) az az információ, amin az algoritmus dolgozik.',
            localization: {
                de: {
                    question: 'Was sind die von einem Algorithmus verwendeten Daten? (ein Wort)',
                    answer: ['Eingabe', 'Input'],
                    explanation: 'Die Eingabe (Input) sind die Informationen, mit denen der Algorithmus arbeitet.'
                },
                ro: {
                    question: 'Care sunt datele folosite de un algoritm? (un cuvânt)',
                    answer: ['intrare', 'input'],
                    explanation: 'Intrarea (input) este informația cu care lucrează algoritmul.'
                },
                en: {
                    question: 'What are the data used by an algorithm called? (one word)',
                    answer: ['input'],
                    explanation: 'The input is the information that the algorithm works on.'
                }
            }
        },
        {
            question: 'Mi az algoritmus által előállított eredmény? (egy szó)',
            answer: ['kimenet'],
            explanation: 'A kimenet (output) az algoritmus futásának végeredménye, a megoldás.',
            localization: {
                de: {
                    question: 'Was ist das von einem Algorithmus erzeugte Ergebnis? (ein Wort)',
                    answer: ['Ausgabe', 'Output'],
                    explanation: 'Die Ausgabe (Output) ist das Endergebnis der Ausführung des Algorithmus, die Lösung.'
                },
                ro: {
                    question: 'Care este rezultatul produs de un algoritm? (un cuvânt)',
                    answer: ['ieșire', 'output'],
                    explanation: 'Ieșirea (output) este rezultatul final al rulării algoritmului, soluția.'
                },
                en: {
                    question: 'What is the result produced by an algorithm called? (one word)',
                    answer: ['output'],
                    explanation: 'The output is the final result of the algorithm's execution, the solution.'
                }
            }
        },
        {
            question: 'Hogyan nevezzük az algoritmusok leírására használt, egyszerűsített programkódot?',
            answer: ['pszeudokód'],
            explanation: 'A pszeudokód egy köztes lépés az ötlet és a valódi programkód között.',
            localization: {
                de: {
                    question: 'Wie nennt man den vereinfachten Programmcode, der zur Beschreibung von Algorithmen verwendet wird?',
                    answer: ['Pseudocode'],
                    explanation: 'Pseudocode ist ein Zwischenschritt zwischen der Idee und dem tatsächlichen Programmcode.'
                },
                ro: {
                    question: 'Cum se numește codul de programare simplificat folosit pentru a descrie algoritmi?',
                    answer: ['pseudocod'],
                    explanation: 'Pseudocodul este un pas intermediar între idee și codul de programare real.'
                },
                en: {
                    question: 'What is the simplified programming code used to describe algorithms called?',
                    answer: ['pseudocode'],
                    explanation: 'Pseudocode is an intermediate step between the idea and the actual program code.'
                }
            }
        },
        {
            question: 'Melyik tulajdonság biztosítja, hogy az algoritmus véges időn belül befejeződik?',
            answer: ['végesség'],
            explanation: 'Egy algoritmusnak nem szabad végtelen ciklusba kerülnie, mindig le kell állnia.',
            localization: {
                de: {
                    question: 'Welche Eigenschaft stellt sicher, dass der Algorithmus in endlicher Zeit endet?',
                    answer: ['Endlichkeit'],
                    explanation: 'Ein Algorithmus darf nicht in eine Endlosschleife geraten, er muss immer anhalten.'
                },
                ro: {
                    question: 'Ce proprietate asigură că algoritmul se termină într-un timp finit?',
                    answer: ['finitudine'],
                    explanation: 'Un algoritm nu trebuie să intre într-o buclă infinită, trebuie să se oprească întotdeauna.'
                },
                en: {
                    question: 'Which property ensures that the algorithm finishes in a finite time?',
                    answer: ['finiteness'],
                    explanation: 'An algorithm must not get into an infinite loop, it must always stop.'
                }
            }
        },
        {
            question: 'Mi a neve annak a rendezési algoritmusnak, ami a szomszédos elemeket cserélgeti?',
            answer: ['buborékrendezés'],
            explanation: 'A buborékrendezés során a nagyobb elemek lassan a lista végére "buborékolnak".',
            localization: {
                de: {
                    question: 'Wie heißt der Sortieralgorithmus, der benachbarte Elemente austauscht?',
                    answer: ['Bubblesort'],
                    explanation: 'Beim Bubblesort "blubbern" die größeren Elemente langsam an das Ende der Liste.'
                },
                ro: {
                    question: 'Care este numele algoritmului de sortare care schimbă elementele adiacente?',
                    answer: ['sortare cu bule'],
                    explanation: 'În sortarea cu bule, elementele mai mari "urcă" încet spre sfârșitul listei.'
                },
                en: {
                    question: 'What is the name of the sorting algorithm that swaps adjacent elements?',
                    answer: ['bubble sort'],
                    explanation: 'In bubble sort, the larger elements slowly "bubble" to the end of the list.'
                }
            }
        }
      ]
    }
  },
  'Digitális Eszközök': {
    meta: {
      id: 'k5-digitalis-eszkozok',
      title: 'Digitális Eszközök',
      description: 'A mindennapi digitális eszközök megismerése és használata.',
      localization: {
        de: { title: 'Digitale Geräte', description: 'Kennenlernen und Verwenden alltäglicher digitaler Geräte.' },
        ro: { title: 'Dispozitive Digitale', description: 'Cunoașterea și utilizarea dispozitivelor digitale de zi cu zi.' },
        en: { title: 'Digital Devices', description: 'Getting to know and using everyday digital devices.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Hálózatok': {
    meta: {
      id: 'k5-halozatok',
      title: 'Hálózatok',
      description: 'A számítógépes hálózatok alapjai, internet.',
      localization: {
        de: { title: 'Netzwerke', description: 'Grundlagen von Computernetzwerken, Internet.' },
        ro: { title: 'Rețele', description: 'Bazele rețelelor de calculatoare, internet.' },
        en: { title: 'Networks', description: 'Basics of computer networks, the internet.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Adatok': {
    meta: {
      id: 'k5-adatok',
      title: 'Adatok',
      description: 'Adatok, információk, adatmennyiségek mértékegységei.',
      localization: {
        de: { title: 'Daten', description: 'Daten, Informationen, Einheiten für Datenmengen.' },
        ro: { title: 'Date', description: 'Date, informații, unități de măsură pentru cantitatea de date.' },
        en: { title: 'Data', description: 'Data, information, units of data volume.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Biztonság': {
    meta: {
      id: 'k5-biztonsag',
      title: 'Biztonság',
      description: 'Digitális biztonság, jelszavak, személyes adatok védelme.',
      localization: {
        de: { title: 'Sicherheit', description: 'Digitale Sicherheit, Passwörter, Schutz persönlicher Daten.' },
        ro: { title: 'Securitate', description: 'Securitate digitală, parole, protecția datelor personale.' },
        en: { title: 'Security', description: 'Digital security, passwords, protection of personal data.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'AI Alapok': {
    meta: {
      id: 'k5-ai-alapok',
      title: 'AI Alapok',
      description: 'Bevezetés a mesterséges intelligencia világába.',
      localization: {
        de: { title: 'KI-Grundlagen', description: 'Einführung in die Welt der künstlichen Intelligenz.' },
        ro: { title: 'Bazele IA', description: 'Introducere în lumea inteligenței artificiale.' },
        en: { title: 'AI Basics', description: 'Introduction to the world of artificial intelligence.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Programozás': {
    meta: {
      id: 'k5-programozas',
      title: 'Programozás',
      description: 'A programozás alapkoncepciói, vizuális programozási nyelvek.',
      localization: {
        de: { title: 'Programmierung', description: 'Grundkonzepte der Programmierung, visuelle Programmiersprachen.' },
        ro: { title: 'Programare', description: 'Concepte de bază ale programării, limbaje de programare vizuală.' },
        en: { title: 'Programming', description: 'Basic concepts of programming, visual programming languages.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Hardver': {
    meta: {
      id: 'k5-hardver',
      title: 'Hardver',
      description: 'A számítógép felépítése, főbb alkatrészek és perifériák.',
      localization: {
        de: { title: 'Hardware', description: 'Aufbau des Computers, Hauptkomponenten und Peripheriegeräte.' },
        ro: { title: 'Hardware', description: 'Structura computerului, componente principale și periferice.' },
        en: { title: 'Hardware', description: 'Computer structure, main components and peripherals.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Szoftverek': {
    meta: {
      id: 'k5-szoftverek',
      title: 'Szoftverek',
      description: 'Operációs rendszerek és alkalmazói programok.',
      localization: {
        de: { title: 'Software', description: 'Betriebssysteme und Anwendungsprogramme.' },
        ro: { title: 'Software', description: 'Sisteme de operare și programe aplicative.' },
        en: { title: 'Software', description: 'Operating systems and application programs.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Adatvédelem': {
    meta: {
      id: 'k5-adatvedelem',
      title: 'Adatvédelem',
      description: 'Személyes adatok védelme az online térben, adatvédelmi szabályok.',
      localization: {
        de: { title: 'Datenschutz', description: 'Schutz persönlicher Daten im Online-Bereich, Datenschutzregeln.' },
        ro: { title: 'Protecția Datelor', description: 'Protecția datelor personale în spațiul online, reguli de confidențialitate.' },
        en: { title: 'Data Protection', description: 'Protection of personal data online, privacy regulations.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Internet': {
    meta: {
      id: 'k5-internet',
      title: 'Internet',
      description: 'Az internet működése, szolgáltatásai és használata.',
      localization: {
        de: { title: 'Internet', description: 'Funktionsweise, Dienste und Nutzung des Internets.' },
        ro: { title: 'Internet', description: 'Funcționarea, serviciile și utilizarea internetului.' },
        en: { title: 'Internet', description: 'How the internet works, its services and usage.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Keresés': {
    meta: {
      id: 'k5-kereses',
      title: 'Keresés',
      description: 'Hatékony információkeresés az interneten.',
      localization: {
        de: { title: 'Suche', description: 'Effiziente Informationssuche im Internet.' },
        ro: { title: 'Căutare', description: 'Căutarea eficientă a informațiilor pe internet.' },
        en: { title: 'Searching', description: 'Effective information searching on the internet.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Szövegszerkesztés': {
    meta: {
      id: 'k5-szovegszerkesztes',
      title: 'Szövegszerkesztés',
      description: 'Szövegszerkesztő programok használata, dokumentumok formázása.',
      localization: {
        de: { title: 'Textverarbeitung', description: 'Verwendung von Textverarbeitungsprogrammen, Formatierung von Dokumenten.' },
        ro: { title: 'Procesare de Text', description: 'Utilizarea procesoarelor de text, formatarea documentelor.' },
        en: { title: 'Word Processing', description: 'Using word processors, formatting documents.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Prezentáció': {
    meta: {
      id: 'k5-prezentacio',
      title: 'Prezentáció',
      description: 'Prezentációkészítő programok használata, előadások készítése.',
      localization: {
        de: { title: 'Präsentation', description: 'Verwendung von Präsentationsprogrammen, Erstellen von Vorträgen.' },
        ro: { title: 'Prezentare', description: 'Utilizarea programelor de prezentare, crearea de expuneri.' },
        en: { title: 'Presentation', description: 'Using presentation software, creating slideshows.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Táblázatkezelés': {
    meta: {
      id: 'k5-tablazatkezeles',
      title: 'Táblázatkezelés',
      description: 'Táblázatkezelő programok alapjai, adatok rendszerezése, egyszerű számítások.',
      localization: {
        de: { title: 'Tabellenkalkulation', description: 'Grundlagen von Tabellenkalkulationsprogrammen, Datenorganisation, einfache Berechnungen.' },
        ro: { title: 'Calcul Tabelar', description: 'Bazele programelor de calcul tabelar, organizarea datelor, calcule simple.' },
        en: { title: 'Spreadsheets', description: 'Basics of spreadsheet programs, organizing data, simple calculations.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Grafika': {
    meta: {
      id: 'k5-grafika',
      title: 'Grafika',
      description: 'Digitális képalkotás, rajzprogramok használata.',
      localization: {
        de: { title: 'Grafik', description: 'Digitale Bilderstellung, Verwendung von Zeichenprogrammen.' },
        ro: { title: 'Grafică', description: 'Crearea de imagini digitale, utilizarea programelor de desen.' },
        en: { title: 'Graphics', description: 'Digital image creation, using drawing programs.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Web': {
    meta: {
      id: 'k5-web',
      title: 'Web',
      description: 'A weblapok felépítése, böngészés, linkek.',
      localization: {
        de: { title: 'Web', description: 'Aufbau von Webseiten, Browsing, Links.' },
        ro: { title: 'Web', description: 'Structura paginilor web, navigare, link-uri.' },
        en: { title: 'Web', description: 'Structure of web pages, browsing, links.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Robotika': {
    meta: {
      id: 'k5-robotika',
      title: 'Robotika',
      description: 'Bevezetés a robotika világába, egyszerű robotok programozása.',
      localization: {
        de: { title: 'Robotik', description: 'Einführung in die Welt der Robotik, Programmierung einfacher Roboter.' },
        ro: { title: 'Robotică', description: 'Introducere în lumea roboticii, programarea roboților simpli.' },
        en: { title: 'Robotics', description: 'Introduction to the world of robotics, programming simple robots.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Logika': {
    meta: {
      id: 'k5-logika',
      title: 'Logika',
      description: 'Logikai műveletek és feltételek a számítástechnikában.',
      localization: {
        de: { title: 'Logik', description: 'Logische Operationen und Bedingungen in der Informatik.' },
        ro: { title: 'Logică', description: 'Operații logice și condiții în informatică.' },
        en: { title: 'Logic', description: 'Logical operations and conditions in computer science.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Adatbázisok': {
    meta: {
      id: 'k5-adatbazisok',
      title: 'Adatbázisok',
      description: 'Az adatbázisok szerepe és alapvető fogalmai.',
      localization: {
        de: { title: 'Datenbanken', description: 'Rolle und grundlegende Konzepte von Datenbanken.' },
        ro: { title: 'Baze de Date', description: 'Rolul și conceptele de bază ale bazelor de date.' },
        en: { title: 'Databases', description: 'The role and basic concepts of databases.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Etika': {
    meta: {
      id: 'k5-etika',
      title: 'Etika',
      description: 'Digitális állampolgárság, etikus viselkedés az online térben.',
      localization: {
        de: { title: 'Ethik', description: 'Digitale Bürgerschaft, ethisches Verhalten im Online-Raum.' },
        ro: { title: 'Etică', description: 'Cetățenie digitală, comportament etic în spațiul online.' },
        en: { title: 'Ethics', description: 'Digital citizenship, ethical behavior in the online space.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Játékfejlesztés': {
    meta: {
      id: 'k5-jatekfejlesztes',
      title: 'Játékfejlesztés',
      description: 'Egyszerű játékok tervezésének és készítésének alapjai.',
      localization: {
        de: { title: 'Spieleentwicklung', description: 'Grundlagen des Designs und der Erstellung einfacher Spiele.' },
        ro: { title: 'Dezvoltare de Jocuri', description: 'Bazele proiectării și creării de jocuri simple.' },
        en: { title: 'Game Development', description: 'Basics of designing and creating simple games.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Kódolás': {
    meta: {
      id: 'k5-kodolas',
      title: 'Kódolás',
      description: 'Kódolási alapismeretek, karakterkódolás.',
      localization: {
        de: { title: 'Codierung', description: 'Grundlagen der Codierung, Zeichencodierung.' },
        ro: { title: 'Codare', description: 'Cunoștințe de bază despre codare, codarea caracterelor.' },
        en: { title: 'Encoding', description: 'Basic knowledge of encoding, character encoding.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Számrendszerek': {
    meta: {
      id: 'k5-szamrendszerek',
      title: 'Számrendszerek',
      description: 'A kettes (bináris) számrendszer alapjai.',
      localization: {
        de: { title: 'Zahlensysteme', description: 'Grundlagen des binären Zahlensystems.' },
        ro: { title: 'Sisteme de Numerație', description: 'Bazele sistemului de numerație binar.' },
        en: { title: 'Number Systems', description: 'Basics of the binary number system.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Multimédia': {
    meta: {
      id: 'k5-multimedia',
      title: 'Multimédia',
      description: 'Szöveg, kép, hang és videó együttes használata.',
      localization: {
        de: { title: 'Multimedia', description: 'Gemeinsame Verwendung von Text, Bild, Ton und Video.' },
        ro: { title: 'Multimedia', description: 'Utilizarea combinată a textului, imaginii, sunetului și videoclipului.' },
        en: { title: 'Multimedia', description: 'Combined use of text, image, sound, and video.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Kommunikáció': {
    meta: {
      id: 'k5-kommunikacio',
      title: 'Kommunikáció',
      description: 'Digitális kommunikációs formák: e-mail, csevegés.',
      localization: {
        de: { title: 'Kommunikation', description: 'Digitale Kommunikationsformen: E-Mail, Chat.' },
        ro: { title: 'Comunicare', description: 'Forme de comunicare digitală: e-mail, chat.' },
        en: { title: 'Communication', description: 'Digital communication forms: e-mail, chat.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Felhő': {
    meta: {
      id: 'k5-felho',
      title: 'Felhő',
      description: 'A felhőalapú szolgáltatások (cloud) alapjai, online tárhelyek.',
      localization: {
        de: { title: 'Cloud', description: 'Grundlagen von Cloud-Diensten, Online-Speicher.' },
        ro: { title: 'Cloud', description: 'Bazele serviciilor cloud, stocare online.' },
        en: { title: 'Cloud', description: 'Basics of cloud services, online storage.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Mobiltechnológia': {
    meta: {
      id: 'k5-mobiltechnologia',
      title: 'Mobiltechnológia',
      description: 'Okostelefonok, tabletek, alkalmazások (appok).',
      localization: {
        de: { title: 'Mobiltechnologie', description: 'Smartphones, Tablets, Anwendungen (Apps).' },
        ro: { title: 'Tehnologie Mobilă', description: 'Smartphone-uri, tablete, aplicații (app-uri).' },
        en: { title: 'Mobile Technology', description: 'Smartphones, tablets, applications (apps).' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Böngészők': {
    meta: {
      id: 'k5-bongeszok',
      title: 'Böngészők',
      description: 'Webböngészők használata, funkciói, biztonsági beállítások.',
      localization: {
        de: { title: 'Browser', description: 'Verwendung von Webbrowsern, ihre Funktionen, Sicherheitseinstellungen.' },
        ro: { title: 'Navigatoare', description: 'Utilizarea navigatoarelor web, funcțiile lor, setări de securitate.' },
        en: { title: 'Browsers', description: 'Using web browsers, their features, security settings.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  },
  'Vírusvédelem': {
    meta: {
      id: 'k5-virusvedelem',
      title: 'Vírusvédelem',
      description: 'A számítógépes vírusok és kártevők, védekezés ellenük.',
      localization: {
        de: { title: 'Virenschutz', description: 'Computerviren und Malware, Schutz dagegen.' },
        ro: { title: 'Protecție Antivirus', description: 'Viruși de calculator și malware, protecție împotriva acestora.' },
        en: { title: 'Virus Protection', description: 'Computer viruses and malware, protection against them.' }
      }
    },
    quiz: {
      multipleChoice: [],
      typing: []
    }
  }
};
