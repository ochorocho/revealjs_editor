/**
 * Curated mermaid sample diagrams for the "Insert example" picker in the BE
 * mermaid editor (MermaidFormEngineElement.js) — the equivalent of the Mermaid
 * Live Editor's "Sample Diagrams" menu. All are valid mermaid v11 and safe
 * under securityLevel:'strict' (no click bindings / raw HTML).
 */
export const MERMAID_EXAMPLES = [
    {
        label: 'Flowchart',
        code: `flowchart TD
  A[Start] --> B{Decision}
  B -->|Yes| C[Do a thing]
  B -->|No| D[Do another thing]
  C --> E[End]
  D --> E`,
    },
    {
        label: 'Sequence diagram',
        code: `sequenceDiagram
  participant U as User
  participant S as Server
  U->>S: Request
  S-->>U: Response
  Note over U,S: round trip`,
    },
    {
        label: 'Class diagram',
        code: `classDiagram
  class Animal {
    +String name
    +move()
  }
  class Dog {
    +bark()
  }
  Animal <|-- Dog`,
    },
    {
        label: 'State diagram',
        code: `stateDiagram-v2
  [*] --> Idle
  Idle --> Running : start
  Running --> Idle : stop
  Running --> [*]`,
    },
    {
        label: 'Entity relationship',
        code: `erDiagram
  CUSTOMER ||--o{ ORDER : places
  ORDER ||--|{ LINE_ITEM : contains
  CUSTOMER {
    string name
    string email
  }`,
    },
    {
        label: 'Gantt chart',
        code: `gantt
  title Project plan
  dateFormat YYYY-MM-DD
  section Phase 1
  Research   :a1, 2026-01-01, 7d
  Prototype  :after a1, 5d`,
    },
    {
        label: 'Pie chart',
        code: `pie title Browser share
  "Chrome" : 63
  "Safari" : 20
  "Firefox" : 10
  "Other" : 7`,
    },
    {
        label: 'Mindmap',
        code: `mindmap
  root((Deck))
    Slides
      Cover
      Code
      Diagram
    Themes
      the-caemp
      typo3`,
    },
    {
        label: 'Git graph',
        code: `gitGraph
  commit
  branch develop
  commit
  checkout main
  merge develop
  commit`,
    },
    {
        label: 'User journey',
        code: `journey
  title My working day
  section Morning
    Coffee: 5: Me
    Email: 3: Me
  section Afternoon
    Write code: 4: Me`,
    },
];
