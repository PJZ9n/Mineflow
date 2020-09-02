<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\flowItem\base\PlayerFlowItem;
use aieuo\mineflow\flowItem\base\PlayerFlowItemTrait;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\Button;
use aieuo\mineflow\formAPI\element\ExampleInput;
use aieuo\mineflow\formAPI\element\Input;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\Toggle;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\formAPI\ListForm;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\MapVariable;
use aieuo\mineflow\variable\NumberVariable;
use aieuo\mineflow\variable\StringVariable;
use pocketmine\Player;

class SendMenuForm extends FlowItem implements PlayerFlowItem {
    use PlayerFlowItemTrait;

    protected $id = self::SEND_MENU;

    protected $name = "action.sendMenu.name";
    protected $detail = "action.sendMenu.detail";
    protected $detailDefaultReplace = ["player", "text", "options", "result"];

    protected $category = Category::FORM;

    protected $targetRequired = Recipe::TARGET_REQUIRED_PLAYER;
    protected $returnValueType = self::RETURN_VARIABLE_VALUE;

    /** @var string */
    private $formText;
    /** @var string */
    private $resultName;
    /** @var array */
    private $options;
    /** @var bool */
    private $resendOnClose = false;

    /** @var string */
    private $lastResult;

    public function __construct(string $player = "target", string $text = "", string $options = "", string $resultName = "menu") {
        $this->setPlayerVariableName($player);
        $this->formText = $text;
        $this->options = array_filter(array_map("trim", explode(";", $options)), function (string $o) {
            return $o !== "";
        });
        $this->resultName = $resultName;
    }

    public function setFormText(string $formText) {
        $this->formText = $formText;
    }

    public function getFormText(): string {
        return $this->formText;
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    public function getOptions(): array {
        return $this->options;
    }

    public function setResultName(string $resultName): void {
        $this->resultName = $resultName;
    }

    public function getResultName(): string {
        return $this->resultName;
    }

    public function isDataValid(): bool {
        return $this->getPlayerVariableName() !== "" and $this->formText !== "" and $this->resultName !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getPlayerVariableName(), $this->getFormText(), implode(";", $this->getOptions()), $this->getResultName()]);
    }

    public function execute(Recipe $origin) {
        $this->throwIfCannotExecute();

        $text = $origin->replaceVariables($this->getFormText());
        $resultName = $origin->replaceVariables($this->getResultName());

        $player = $this->getPlayer($origin);
        $this->throwIfInvalidPlayer($player);

        $this->sendForm($origin, $player, $text, $resultName);
        yield false;
        return true;
    }

    /** @noinspection PhpUnusedParameterInspection */
    private function sendForm(Recipe $origin, Player $player, string $text, string $resultName, array $errors = []) {
        $buttons = [];
        foreach ($this->options as $option) {
            $buttons[] = new Button($option);
        }

        (new ListForm($text))
            ->setContent($text)
            ->setButtons($buttons)->onReceive(function (Player $player, int $data) use ($origin, $resultName) {
                $this->lastResult = (string)$data;

                $variable = new MapVariable([
                    "id" => new NumberVariable($data, "id"),
                    "text" => new StringVariable($this->options[$data], "text"),
                ], $resultName, $this->options[$data]);
                $origin->addVariable($variable);
                $origin->resume();
            })->onClose(function (Player $player) use ($origin, $text, $resultName) {
                if ($this->resendOnClose) $this->sendForm($origin, $player, $text, $resultName, [["@form.insufficient", 0]]);
            })->addErrors($errors)->show($player);
    }

    public function getEditForm(array $default = [], array $errors = []): Form {
        $contents = [
            new Label($this->getDescription()),
            new ExampleInput("@flowItem.form.target.player", "target", $default[1] ?? $this->getPlayerVariableName(), true),
            new ExampleInput("@flowItem.form.resultVariableName", "input", $default[2] ?? $this->getResultName(), true),
            new ExampleInput("@action.sendInput.form.text", "aieuo", $default[3] ?? $this->getFormText(), true),
        ];
        foreach ($this->getOptions() as $i => $option) {
            $contents[] = new Input(Language::get("customForm.dropdown.option", [$i]), Language::get("form.example", ["aieuo"]), $option);
        }
        $contents[] = new ExampleInput("@customForm.dropdown.option.add", "aeiuo");
        $contents[] = new Toggle("@action.sendInput.form.resendOnClose", $default[4] ?? $this->resendOnClose);
        $contents[] = new Toggle("@form.cancelAndBack");

        return (new CustomForm($this->getName()))
            ->setContents($contents)->addErrors($errors);
    }

    public function parseFromFormData(array $data): array {
        $errors = [];
        array_shift($data);
        $target = array_shift($data);
        $resultName = array_shift($data);
        $text = array_shift($data);
        $cancel = array_pop($data);
        $resendOnClose = array_pop($data);
        $add = array_filter(array_map("trim", explode(";", array_pop($data))), function (string $o) { return $o !== ""; });

        $options = array_filter($data, function (string $o) { return $o !== ""; });
        $options = array_merge($options, $add);
        return ["contents" => [$target, $resultName, $text, $options, $resendOnClose], "cancel" => $cancel, "errors" => $errors];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setPlayerVariableName($content[0]);
        $this->setResultName($content[1]);
        $this->setFormText($content[2]);
        $this->setOptions($content[3]);
        $this->resendOnClose = $content[4];
        return $this;
    }

    public function serializeContents(): array {
        return [
            $this->getPlayerVariableName(),
            $this->getResultName(),
            $this->getFormText(),
            $this->getOptions(),
            $this->resendOnClose
        ];
    }

    public function getReturnValue(): string {
        return $this->lastResult;
    }
}