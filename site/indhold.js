/*
  ============================================================
   INDHOLD TIL HJEMMESIDEN
   Her retter du al tekst på siden. Du behøver ikke røre andre filer.

   Regler:
   - Ret kun teksten mellem anførselstegnene "sådan her".
   - Behold kommaerne for enden af linjerne.
   - Billeder lægges i mappen "billeder" og skrives som "billeder/navn.jpg".
   - Gem filen og upload den. Færdig.
  ============================================================
*/

const INDHOLD = {

  // ---------- FORSIDE ----------
  forside: {
    undertitel: "Teater for unge",
  },

  // ---------- OM SCENARIO ----------
  // "kort" vises på forsiden. "afsnit" vises på siden "Om scenario" (om.html).
  // Hvert afsnit har en overskrift og en liste af tekststykker (ét pr. afsnit).
  om: {
    overskrift: "Om scenario",
    kort: "Scenario er en ny teaterforening i Odense – skabt af unge, for unge – med kærlighed til scenekunst, musical og alt det fede, der opstår, når mennesker mødes om noget kreativt.",
    knap: "Læs mere om Scenario",
    afsnit: [
      {
        overskrift: "Visioner og mål",
        undertitel: "Scenario – for dig, der vil mere med scenekunst.",
        tekst: [
          "Scenario er en ny teaterforening i Odense – skabt af unge, for unge – med kærlighed til scenekunst, musical og alt det fede, der opstår, når mennesker mødes om noget kreativt.",
          "Hos os handler det ikke kun om at stå på en scene – det handler om at udvikle sig. Vi tager kunsten seriøst og giver dig muligheden for at arbejde målrettet med dit talent. Du får plads til at udforske, vokse og blive dygtigere – uanset om du drømmer om scenen eller livet bag tæppet.",
          "Vi laver også mindre projekter og arbejder løbende med idéudvikling, så der er masser af muligheder for at prøve kræfter med teater i alle former og farver.",
          "Scenario er et sted, hvor du kan finde din stemme, finpudse dit udtryk og tage dine første – eller næste – seriøse skridt mod en fremtid med scenekunst.",
        ],
      },
      {
        overskrift: "Om foreningen",
        undertitel: "",
        tekst: [
          "Selvom Scenario er en nystiftet forening, er den på ingen måde uden erfaring.",
          "Tværtimod hviler den på et solidt fundament af stærke kræfter med mange års virke i det danske forenings- og kulturliv. Foreningen er båret frem af nøglepersoner, der tidligere har været drivende i succesfulde lignende tiltag, heriblandt; Elite Teater, Nyborg Voldspil, Frøbjerg Festspil og mange andre foreninger og projekter, der har sat tydelige aftryk på det lokale kulturliv med deres høje kunstneriske niveau og evne til at engagere unge.",
          "Denne erfaring bringes nu videre i Scenario, hvor det kreative, inkluderende og udviklende arbejde fortsætter i nye rammer. Foreningen har dermed, på trods af sin unge alder, et erfarent og passioneret bagland, der både forstår de praktiske, organisatoriske og kunstneriske aspekter af foreningsdrevet scenekunst.",
          "Det er netop denne kombination af ny energi og gammel erfaring, der gør Scenario til en lovende og bæredygtig aktør i kulturlivet – med ambitioner om at skabe varige og meningsfulde tilbud for både deltagere og publikum.",
        ],
      },
    ],
  },

  // ---------- EVENTS & FORESTILLINGER ----------
  // Nyeste først. Kopiér en blok { ... }, for at tilføje et nyt event.
  //   fremhaevet: true  -> vises stort på forsiden (sæt det kun på ét event)
  //   start: bruges til nedtælling. Format "ÅÅÅÅ-MM-DDTTT:MM". Lad stå tom "" hvis ingen.
  //   laesMereLink: link til siden om forestillingen ("læs mere her"). Lad stå tom "" hvis ingen.
  //   billetLink: link til billetsalg. Lad stå tom "" hvis der ikke sælges billetter.
  events: [
    {
      titel: "Final Call",
      type: "Teaterkoncert (vinter 2026)",
      datoer: "17. – 19. december 2026",
      sted: "Den Fynske Opera",
      start: "2026-12-17T19:30",
      spoergsmaal: "Hvis verden sluttede om fire timer – hvem ville du så være?",
      tekst: "Til december sætter vi en teaterkoncert: \"Final Call\"! Det er ikke en fortælling om en katastrofe. Det er en fortælling om, hvad der sker, når mennesker ikke længere har noget at miste.",
      billede: "billeder/final-call-banner.webp",
      laesMereLink: "https://www.teaterscenario.dk/teaterkoncert2026/",
      billetLink: "",
      fremhaevet: true,
    },
    {
      titel: "Natten til Torsdag",
      type: "Scenekunstnerisk projekt for unge",
      datoer: "2026",
      sted: "",
      start: "",
      spoergsmaal: "",
      tekst: "Et scenekunstnerisk projekt skabt af og med unge.",
      billede: "billeder/praesentation.png",
      laesMereLink: "https://www.teaterscenario.dk/natten-til-torsdag/",
      billetLink: "",
      fremhaevet: false,
    },
    {
      titel: "Heathers The Musical",
      type: "Musical",
      datoer: "2025",
      sted: "",
      start: "",
      spoergsmaal: "",
      tekst: "Den mørke, sjove kultmusical om magt og popularitet i high school.",
      billede: "billeder/praesentation.png",
      laesMereLink: "https://www.teaterscenario.dk/heathers-the-musical/",
      billetLink: "",
      fremhaevet: false,
    },
  ],

  // ---------- VIL DU VÆRE MED? ----------
  medvirk: {
    overskrift: "Vil du være med?",
    tekst: "Vi leder altid efter nye folk – på scenen, i bandet, til lys og lyd, kostumer, scenografi og meget mere.",
    roller: ["Skuespil", "Sang", "Band", "Lys & lyd", "Kostumer", "Scenografi"],
    knap: "Skriv til os",
  },

  // ---------- SPONSORER & NETVÆRK ----------
  // Læg logoet i mappen "billeder/sponsorer" med præcis det filnavn, der står ved logo.
  // Mangler logoet, vises navnet som tekst i stedet.
  sponsorer: [
    { navn: "Odense Kommune", logo: "billeder/sponsorer/odense_komune.webp" },
    { navn: "Odense Teater", logo: "billeder/sponsorer/odense_teator.webp" },
    { navn: "Odense Sommer Revy", logo: "billeder/sponsorer/odense_sommer_revy.webp" },
    { navn: "Viby Efterskole", logo: "billeder/sponsorer/viby_efterskole.webp" },
    { navn: "Holy Cow Media", logo: "billeder/sponsorer/holy-cow-media.webp" },
    { navn: "MIC Productions", logo: "billeder/sponsorer/mic-productions.webp" },
    { navn: "7-Eleven", logo: "billeder/sponsorer/seven_eleven.webp" },
    { navn: "AKCentralen", logo: "billeder/sponsorer/akcentralen.webp" },
  ],

  // ---------- KONTAKT ----------
  kontakt: {
    email: "kontakt@teaterscenario.dk",
    cvr: "45117626",
    instagram: "",   // fx "https://instagram.com/teaterscenario"
    facebook: "",
  },
};
