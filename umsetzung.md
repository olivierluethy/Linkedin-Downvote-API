LinkedIn Downvote

LinkedIn hat sein User-Overlay wieder komplett verändert, sodass sich die drei Buttons nicht mehr eindeutig einem Bereich zuordnen lassen. Sie haben sogar das span-Element verändert, damit man es mit einer ID nicht mehr finden kann, da diese entfernt wurde. Also nicht das span-Element selbst, sondern die ID als Attribut.

Es gibt nur noch eine eindeutige Art, wie man Beiträge am leichtesten finden kann. Das Problem ist, dass sich das bei einem nächsten Update vermutlich wieder komplett ändern wird.

Daher muss man sich anschauen, was sich offensichtlich nicht mehr so schnell verändern wird. Was ist dabei immer gleich geblieben? Die Benennung der Buttons sowie deren Attribute.

Daher hatte ich die Idee, die Abfolge der Buttons zu verwenden, um zu erkennen, wann es sich um einen ganzen Post handelt, ab wann um mehrere und ab wann genau nur um einen.

Die Abfolge sieht immer so aus: Like, Repost, Kommentar, Teilen. Beim Share-Button ist es natürlich ein anderes Attribut, um ihn von den drei restlichen Buttons unterscheiden zu können. Daher habe ich ein Programm geschrieben, das auf der gesamten LinkedIn-Webseite prüft, welche Buttons es erkennt. Es hat sie auch erkannt und nun folgt die Abfolge z. B. dieser Ausgabe: Like, Repost, Kommentar, Like, Repost, Kommentar usw.

In diesem beispiel sieht man, dass es zwei Mal die gleichen buttons sind, daher wird auch die Unterscheidung auf zwei Unterschiedliche Posts gewährt.

Den sobald eine button abfolge stimmt, oder es sich zumal mindestens ein button erkannt wird mit einer duplikaten abfolge, so können man noch Post weiterhin erkennen.

Sobald man diesen einen Post erkennt, bringt man es natürlich in außerordentliche Erwägung, sich dem gesamten Post zu widmen, zum Beispiel anhand der Zahl der Reaktionen. Was wäre klüger? Sich mit einer speziellen Spezifikation herumzuirren, nur um das Element am Ende erneut suchen zu müssen, da LinkedIn es sich selbst sehr leicht macht, schnell ein paar Elemente an ihrem Overlay zu verändern?

Das Reaktionselement beinhaltet ja immer eine Zahl. Daher müsste man ja eigentlich nur vom bereich der erkannten Buttons → die dazu beitragen für die Erkennung von jedem Post, könne man doch einfach Element für element via parentElement jedes vereinzelte <span> Element durchgehen und schauen, ob es erstens überhaupt ein solches Element gibt und wenn ja, beinhaltet es auch eine Zahl?

Dies haben wir dann auch gemacht. Wir gingen oberhalb etage für etage jede türe, jede fläche, jede treppe durch bis man dann auf ein Element stosst, das ein <span> Element ist und sich auch darin eine effektive Zahl beinhaltet und nicht ein Buchstabe.
