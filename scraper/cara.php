<?php

class cara{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("cara");
	}
	
	public function getfilters($page){
		
		return [
			"sort" => [
				"display" => "Sort by",
				"option" => [
					"Top" => "Top",
					"MostRecent" => "Most Recent"
				]
			],
			"type" => [
				"display" => "Post type",
				"option" => [
					"any" => "Any type",
					"portfolio" => "Portfolio", // {"posts":["portfolio"]}
					"timeline" => "Timeline" // {"posts":["timeline"]}
				]
			],
			"fields" => [
				"display" => "Field/Medium",
				"option" => [
					"any" => "Any field",
					"2D" => "2D Work",
					"3D" => "3D Work",
					"3DPrinting" => "3D Printing",
					"Acrylic" => "Acrylic",
					"AlcoholMarkers" => "Alcohol Markers",
					"Animation" => "Animation",
					"Chalk" => "Chalk",
					"Charcoal" => "Charcoal",
					"Colored pencil" => "Colored pencil",
					"Conte" => "Conte",
					"Crayon" => "Crayon",
					"Digital" => "Digital",
					"Gouache" => "Gouache",
					"Ink" => "Ink",
					"MixedMedia" => "Mixed-Media",
					"Oil" => "Oil",
					"Oil-based Markers" => "Oil-based Markers",
					"Other" => "Other",
					"Pastels" => "Pastels",
					"Photography" => "Photography",
					"Sculpture" => "Sculpture",
					"Sketches" => "Sketches",
					"Tattoos" => "Tattoos",
					"Traditional" => "Traditional",
					"VFX" => "VFX",
					"Watercolor" => "Watercolor"
				]
			],
			"category" => [
				"display" => "Category",
				"option" => [
					"any" => "Any category",
					"3DScanning" => "3D Scanning",
					"Abstract" => "Abstract",
					"Adoptable" => "Adoptable",
					"Anatomy" => "Anatomy",
					"Animals" => "Animals",
					"Anime" => "Anime",
					"App" => "App",
					"ArchitecturalConcepts" => "Architectural Concepts",
					"ArchitecturalVisualization" => "Architectural Visualization",
					"AugmentedReality" => "Augmented Reality",
					"Automotive" => "Automotive",
					"BoardGameArt" => "Board Game Art",
					"BookIllustration" => "Book Illustration",
					"CardGameArt" => "Card Game Art",
					"CeramicsPottery" => "Ceramics/Pottery",
					"CharacterAnimation" => "Character Animation",
					"CharacterDesign" => "Character Design",
					"CharacterModeling" => "Character Modeling",
					"ChildrensArt" => "Children's Illustration",
					"Collectibles" => "Collectibles",
					"ColoringPage" => "Coloring Page",
					"ComicArt" => "Comic Art",
					"ConceptArt" => "Concept Art",
					"Cosplay" => "Cosplay",
					"CostumeDesign" => "Costume Design",
					"CoverArt" => "Cover Art",
					"Creatures" => "Creatures",
					"Diorama" => "Diorama",
					"EditorialIllustration" => "Editorial Illustration",
					"EmbroiderySewing" => "Embroidery/Sewing",
					"EnvironmentalConceptArt" => "Environmental Concept Art",
					"EnvironmentalConceptDesign" => "Environmental Concept Design",
					"FanArt" => "Fan Art",
					"Fantasy" => "Fantasy",
					"Fashion" => "Fashion",
					"FashionStyling" => "Fashion Styling",
					"FiberArts" => "Fiber Arts",
					"Furry" => "Furry",
					"GameArt" => "Game Art",
					"GameplayDesign" => "Gameplay Design",
					"GamesEnvironmentArt" => "Games Environment Art",
					"Gem" => "Gem",
					"GraphicDesign" => "Graphic Design",
					"Handicraft" => "Handicraft",
					"HairStyling" => "Hair Styling",
					"HardSurface" => "Hard Surface",
					"Horror" => "Horror",
					"Illustration" => "Illustration",
					"IllustrationVisualization" => "Illustration Visualization",
					"IndustrialDesign" => "Industrial Design",
					"Jewelry" => "Jewelry",
					"KnittingCrochet" => "Knitting/Crochet",
					"Landscape" => "Landscape",
					"LevelDesign" => "Level Design",
					"Lighting" => "Lighting",
					"Makeup" => "Makeup",
					"Manga" => "Manga",
					"MapsCartography" => "Maps/Cartography",
					"MattePainting" => "Matte Painting",
					"Materials" => "Materials",
					"MechanicalDesign" => "Mechanical Design",
					"Medical" => "Medical",
					"Mecha" => "Mecha",
					"MiniatureArt" => "Miniature Art",
					"MotionGraphics" => "Motion Graphics",
					"FrescoMurals" => "Fresco/Murals",
					"Natural" => "Natural",
					"Original Character" => "Original Character",
					"Overlay" => "Overlay",
					"PleinAir" => "Plein Air",
					"Photogrammetry" => "Photogrammetry",
					"PixelArt" => "Pixel Art",
					"Portraits" => "Portraits",
					"Props" => "Props",
					"ProductDesign" => "Product Design",
					"PublicDomain" => "Public Domain or Royalty Free",
					"Real-Time3DEnvironmentArt" => "Real-Time 3D Environment Art",
					"Realism" => "Realism",
					"ScienceFiction" => "Science Fiction",
					"ScientificVisualization" => "Scientific Visualization",
					"Scripts" => "Scripts",
					"StillLife" => "Still Life",
					"Storyboards" => "Storyboards",
					"Stylized" => "Stylized",
					"Surreal" => "Surreal",
					"TechnicalArt" => "Technical Art",
					"Textures" => "Textures",
					"Tools" => "Tools",
					"Toys" => "Toys",
					"ToyPackaging" => "Toy Packaging",
					"Tutorials" => "Tutorials",
					"UIArt" => "User Interface (UI) Art",
					"UrbanSketch" => "Urban Sketch",
					"VFXforAnimation" => "VFX for Animation",
					"VFXforFilm" => "VFX for Film",
					"VFXforGames" => "VFX for Games",
					"VFXforRealTime" => "VFX for Real-Time",
					"VFXforTV" => "VFX for TV",
					"Vehicles" => "Vehicles",
					"VirtualReality" => "Virtual Reality",
					"VisualDevelopment" => "Visual Development",
					"VoxelArt" => "Voxel Art",
					"Vtubers" => "Vtubers",
					"WIP" => "WIP (Work in Progress)",
					"Web" => "Web",
					"Weapons" => "Weapons",
					"Wildlife" => "Wildlife",
					"Woodcutting" => "Woodcutting"
				]
			],
			"software" => [
				"display" => "Software",
				"option" => [
					"any" => "Any software",
					"123D" => "123D",
					"123DCatch" => "123D Catch",
					"3DBee" => "3DBee",
					"3DCoat" => "3DCoat",
					"3DCoatPrint" => "3DCoatPrint",
					"3DCoatTextura" => "3DCoatTextura",
					"3DEqualizer" => "3DEqualizer",
					"3DFZephyr" => "3DF Zephyr",
					"3Delight" => "3Delight",
					"3dpeople" => "3dpeople",
					"3dsMax" => "3ds Max",
					"3DSPaint" => "3DS Paint",
					"ACDSeeCanvas" => "ACDSee Canvas",
					"AbletonLive" => "Ableton Live",
					"Acrobat" => "Acrobat",
					"AdobeDraw" => "Adobe Draw",
					"AdobeFlash" => "Adobe Flash",
					"AdobeFresco" => "Adobe Fresco",
					"AdobeSubstance3Dassets" => "Adobe Substance 3D assets",
					"AdobeXD" => "Adobe XD",
					"AffinityDesigner" => "Affinity Designer",
					"AffinityPhoto" => "Affinity Photo",
					"AfterEffects" => "After Effects",
					"Akeytsu" => "Akeytsu",
					"Alchemy" => "Alchemy",
					"AliasDesign" => "Alias Design",
					"AlightMotion" => "Alight Motion",
					"Amadine" => "Amadine",
					"Amberlight" => "Amberlight",
					"Animate" => "Animate",
					"AnimationMaster" => "Animation:Master",
					"AnimeStudio" => "Anime Studio",
					"Apophysis" => "Apophysis",
					"ArchiCAD" => "ArchiCAD",
					"Arion" => "Arion",
					"ArionFX" => "ArionFX",
					"Arnold" => "Arnold",
					"ArtEngine" => "ArtEngine",
					"ArtFlow" => "ArtFlow",
					"ArtRage" => "ArtRage",
					"ArtstudioPro" => "Artstudio Pro",
					"Artweaver" => "Artweaver",
					"Aseprite" => "Aseprite",
					"Audition" => "Audition",
					"AutoCAD" => "AutoCAD",
					"AutodeskSketchBook" => "Autodesk SketchBook",
					"AvidMediaComposer" => "Avid Media Composer",
					"AzPainter" => "AzPainter",
					"babylonjs" => "babylon.js",
					"BalsamiqMockup" => "Balsamiq Mockup",
					"Bforartists" => "Bforartists",
					"BlackInk" => "Black Ink",
					"BlackmagicDesignFusion" => "Blackmagic Design Fusion",
					"Blender" => "Blender",
					"Blender DeepPaint" => "Blender DeepPaint",
					"BlenderGreasePencil" => "Blender Grease Pencil",
					"Blockbench" => "Blockbench",
					"BodyPaint" => "BodyPaint",
					"Boxcutter" => "Boxcutter",
					"BraidMaker" => "Braid Maker",
					"BrickLinkStudio" => "BrickLink Studio",
					"Bridge" => "Bridge",
					"Brushifyio" => "Brushify.io",
					"C" => "C",
					"C#" => "C#",
					"C++" => "C++",
					"CACANi" => "CACANi",
					"CLIPSTUDIOPAINT" => "CLIP STUDIO PAINT",
					"CLO" => "CLO",
					"CRYENGINE" => "CRYENGINE",
					"Callipeg" => "Callipeg",
					"Canva" => "Canva",
					"CaptureOne" => "Capture One",
					"CartoonAnimator" => "Cartoon Animator",
					"Carveco" => "Carveco",
					"Cavalry" => "Cavalry",
					"Chaotica" => "Chaotica",
					"CharacterAnimator" => "Character Animator",
					"CharacterCreator" => "Character Creator",
					"Cinema4D" => "Cinema 4D",
					"ClarisseiFX" => "Clarisse iFX",
					"Coiffure" => "Coiffure",
					"ColorsLive" => "Colors Live",
					"Combustion" => "Combustion",
					"Construct2" => "Construct 2",
					"Core" => "Core",
					"CorelPainter" => "Corel Painter",
					"CorelDRAWGraphicsSuite" => "CorelDRAW Graphics Suite",
					"CoronaRenderer" => "Corona Renderer",
					"ProMotionNG" => "Cosmigo Pro Motion NG",
					"CrazyBump" => "CrazyBump",
					"Crocotile3D" => "Crocotile 3D",
					"Curvy3D" => "Curvy 3D",
					"Cycles4D" => "Cycles 4D",
					"Darkroom" => "Darkroom",
					"DAZStudio" => "DAZ Studio",
					"DDO" => "DDO",
					"DECIMA" => "DECIMA",
					"Darktable" => "Darktable",
					"DaVinciResolve" => "DaVinci Resolve",
					"Dimension" => "Dimension",
					"DragonBones" => "DragonBones",
					"Dragonframe" => "Dragonframe",
					"Drawpile" => "Drawpile",
					"Dreams" => "Dreams",
					"Dreamweaver" => "Dreamweaver",
					"DxOPhotoLab" => "DxO PhotoLab",
					"ECycles" => "E-Cycles",
					"EmberGen" => "EmberGen",
					"Encore" => "Encore",
					"Expresii" => "Expresii",
					"FStorm" => "FStorm",
					"FadeIn" => "FadeIn",
					"Feather3D" => "Feather 3D",
					"FiberShop" => "FiberShop",
					"Figma" => "Figma",
					"FilmoraWondershare" => "Filmora Wondershare",
					"FilterForge" => "Filter Forge",
					"FinalCutPro" => "Final Cut Pro",
					"FinalDraft" => "Final Draft",
					"finalRender" => "finalRender",
					"FireAlpaca" => "FireAlpaca",
					"Fireworks" => "Fireworks",
					"FlamePainter" => "Flame Painter",
					"Flash" => "Flash",
					"FlipaClip" => "FlipaClip",
					"FlipnoteStudio" => "Flipnote Studio",
					"Fluent" => "Fluent",
					"ForestPack" => "Forest Pack",
					"FormZ" => "Form-Z",
					"Fractorium" => "Fractorium",
					"FreeCAD" => "FreeCAD",
					"FreeHand" => "FreeHand",
					"Forger" => "Forger",
					"FrostbiteEngine" => "Frostbite Engine",
					"fSpy" => "fSpy",
					"FumeFX" => "FumeFX",
					"Fusion360" => "Fusion 360",
					"GIMP" => "GIMP",
					"GSCurveTools" => "GS CurveTools",
					"GSToolbox" => "GS Toolbox",
					"Gaea" => "Gaea",
					"GameTextures" => "Game Textures",
					"GameMakerStudio" => "GameMaker: Studio",
					"GarageFarmNET" => "GarageFarm.NET",
					"GeoGlyph" => "GeoGlyph",
					"GigapixelAl" => "Gigapixel Al",
					"Glaxnimate" => "Glaxnimate",
					"GnomePaint" => "Gnome Paint",
					"Godot" => "Godot",
					"Goxel" => "Goxel",
					"Graphite" => "Graphite",
					"Graswald" => "Graswald",
					"GravitySketch" => "Gravity Sketch",
					"GuerillaRender" => "GuerillaRender",
					"HDRLightStudio" => "HDR Light Studio",
					"HairStrandDesigner" => "Hair Strand Designer",
					"HairTGHairFur" => "HairTG - Hair &amp; Fur",
					"HairTGSurfaceFeatherEdition" => "HairTG - Surface, Feather Edition",
					"HairTGSurfaceHairEdition" => "HairTG - Surface, Hair Edition",
					"Handplane" => "Handplane",
					"Hansoft" => "Hansoft",
					"HardOps" => "Hard Ops",
					"HardMesh" => "HardMesh",
					"Harmony" => "Harmony",
					"HeavypaintWebbypaint" => "Heavypaint/Webbypaint",
					"HelloPaint" => "HelloPaint",
					"HeliconFocus" => "Helicon Focus",
					"Hexels" => "Hexels",
					"HiPaint" => "HiPaint",
					"Houdini" => "Houdini",
					"HydraRenderer" => "Hydra Renderer",
					"iArtbook" => "iArtbook",
					"IbisPaint" => "ibisPaint",
					"Ideas" => "Ideas",
					"IllustStudio" => "Illust Studio",
					"Illustrator" => "Illustrator",
					"IllustratorDraw" => "Illustrator Draw",
					"InDesign" => "InDesign",
					"Inochi2D" => "Inochi2D",
					"InVision" => "InVision",
					"InVisionCraft" => "InVision Craft",
					"InfinitePainter" => "Infinite Painter",
					"Inkscape" => "Inkscape",
					"Inspirit" => "Inspirit",
					"InstaLOD" => "InstaLOD",
					"InstaMAT" => "InstaMAT",
					"InstantLightRealtimePBR" => "Instant Light Realtime PBR",
					"InstantMeshes" => "Instant Meshes",
					"InstantTerra" => "Instant Terra",
					"Inventor" => "Inventor",
					"Iray" => "Iray",
					"JWildfire" => "JWildfire",
					"Java" => "Java",
					"Jira" => "Jira",
					"JumpPaint" => "Jump Paint by MediBang",
					"JSPaint" => "JS Paint",
					"Katana" => "Katana",
					"Keyshot" => "Keyshot",
					"KidPix" => "Kid Pix",
					"KitBash3D" => "KitBash3D",
					"Knald" => "Knald",
					"Kodon" => "Kodon",
					"KolourPaint" => "KolourPaint",
					"Krakatoa" => "Krakatoa",
					"KRESKA" => "KRESKA",
					"Krita" => "Krita",
					"LensStudio" => "Lens Studio",
					"LibreSprite" => "LibreSprite",
					"LightWave3D" => "LightWave 3D",
					"Lightroom" => "Lightroom",
					"Linearity" => "Linearity",
					"LiquiGen" => "LiquiGen",
					"Live2DCubism" => "Live2D Cubism",
					"LookatmyHair" => "Look at my Hair",
					"Lotpixel" => "Lotpixel",
					"Lumion" => "Lumion",
					"LuxRender" => "LuxRender",
					"MacPaint" => "MacPaint",
					"MagicaCSG" => "MagicaCSG",
					"MagicaVoxel" => "MagicaVoxel",
					"Magma" => "Magma",
					"MakeHuman" => "MakeHuman",
					"Malmal" => "Malmal",
					"Mandelbulb3D" => "Mandelbulb 3D",
					"Mandelbulber" => "Mandelbulber",
					"MangaStudio" => "Manga Studio",
					"Mari" => "Mari",
					"MarmosetToolbag" => "Marmoset Toolbag",
					"MarvelousDesigner" => "Marvelous Designer",
					"MasterpieceStudioPro" => "Masterpiece Studio Pro",
					"MasterpieceVR" => "MasterpieceVR",
					"Maverick" => "Maverick",
					"MaxwellRender" => "Maxwell Render",
					"Maya" => "Maya",
					"MediBangPaint" => "MediBang Paint",
					"MediumbyAdobe" => "Medium by Adobe",
					"Megascans" => "Megascans",
					"mentalray" => "mental ray",
					"MeshLab" => "MeshLab",
					"Meshroom" => "Meshroom",
					"MetaHumanCreator" => "MetaHuman Creator",
					"Metashape" => "Metashape",
					"MightyBake" => "MightyBake",
					"MikuMikuDance" => "MikuMikuDance",
					"Minecraft" => "Minecraft",
					"Mischief" => "Mischief",
					"Mixamo" => "Mixamo",
					"Mixer" => "Mixer",
					"MoI3D" => "MoI3D",
					"Mocha" => "Mocha",
					"Modo" => "Modo",
					"Moho" => "Moho",
					"MotionBuilder" => "MotionBuilder",
					"Mudbox" => "Mudbox",
					"Muse" => "Muse",
					"MSPaint" => "MS Paint",
					"MyPaint" => "MyPaint",
					"NDO" => "NDO",
					"NX" => "NX",
					"NdotCAD" => "NdotCAD",
					"NintendoNotes" => "Nintendo Notes",
					"NomadSculpt" => "Nomad Sculpt",
					"Notability" => "Notability",
					"Nuke" => "Nuke",
					"Nvil" => "Nvil",
					"OctaneRender" => "Octane Render",
					"Omniverse" => "Omniverse",
					"OmniverseCreate" => "Omniverse Create",
					"ON1PhotoRAW" => "ON1 Photo RAW",
					"Open3DEngine" => "Open 3D Engine",
					"OpenCanvas" => "OpenCanvas",
					"OpenGL" => "OpenGL",
					"OpenToonz" => "OpenToonz",
					"Ornatrix" => "Ornatrix",
					"OsciRender" => "Osci-Render",
					"OurPaint" => "Our Paint",
					"PBRMAX" => "PBRMAX",
					"PFTrack" => "PFTrack",
					"PTGui" => "PTGui",
					"Paintbrush" => "Paintbrush",
					"PaintNET" => "Paint.NET",
					"PaintShopPro" => "PaintShop Pro",
					"PaintToolSAI" => "Paint Tool SAI",
					"PaintstormStudio" => "Paintstorm Studio",
					"Paper" => "Paper",
					"Pencil2D" => "Pencil2D",
					"Penpot" => "Penpot",
					"PhoenixFD" => "Phoenix FD",
					"Phonto" => "Phonto",
					"PhotoLab2" => "PhotoLab 2",
					"Photopea" => "Photopea",
					"Photoscan" => "Photoscan",
					"Photoshop" => "Photoshop",
					"PhotoshopElements" => "Photoshop Elements",
					"PicoCAD" => "picoCAD",
					"PicoCAD2" => "picoCAD 2",
					"Pinta" => "Pinta",
					"Piskel" => "Piskel",
					"Pixilart" => "Pixilart",
					"Pixelitor" => "Pixelitor",
					"Pixelmator" => "Pixelmator",
					"Pixelorama" => "Pixelorama",
					"PixivSketch" => "pixiv Sketch",
					"Pixquare" => "Pixquare",
					"PlantCatalog" => "PlantCatalog",
					"PlantFactory" => "PlantFactory",
					"Plasticity" => "Plasticity",
					"PNGtuberPlus" => "PNGtuber Plus",
					"Poliigon" => "Poliigon",
					"Polybrush" => "Polybrush",
					"PopcornFx" => "PopcornFx",
					"Poser" => "Poser",
					"Premiere" => "Premiere",
					"PremiereElements" => "Premiere Elements",
					"PresagisCreator" => "Presagis Creator",
					"ProTools" => "Pro Tools",
					"Procreate" => "Procreate",
					"ProcreateDreams" => "Procreate Dreams",
					"Producer" => "Producer",
					"PrometheanAI" => "Promethean AI",
					"PureRef" => "PureRef",
					"Python" => "Python",
					"PyxelEdit" => "PyxelEdit",
					"QuadRemesher" => "Quad Remesher",
					"QuarkXPress" => "QuarkXPress",
					"Qubicle" => "Qubicle",
					"Quill" => "Quill",
					"QuixelBridge" => "Quixel Bridge",
					"QuixelMegascans" => "Quixel Megascans",
					"QuixelMixer" => "Quixel Mixer",
					"QuixelSuite" => "Quixel Suite",
					"R3DSWrap" => "R3DS Wrap",
					"R3DSZWRAP" => "R3DS ZWRAP",
					"RDTextures" => "RD-Textures",
					"RailClone" => "RailClone",
					"RealFlow" => "RealFlow",
					"RealisticPaintStudio" => "Realistic Paint Studio",
					"RealityCapture" => "RealityCapture",
					"RealityScan" => "RealityScan",
					"RealtimeBoard" => "Realtime Board",
					"Rebelle" => "Rebelle",
					"Redshift" => "Redshift",
					"RenderMan" => "RenderMan",
					"RenderNetwork" => "Render Network",
					"Revit" => "Revit",
					"Rhino" => "Rhino",
					"Rhinoceros" => "Rhinoceros",
					"RizomUV" => "RizomUV",
					"RoughAnimator" => "Rough Animator",
					"SamsungNotes" => "Samsung Notes",
					"SamsungPENUP" => "Samsung PENUP",
					"ScansLibrary" => "ScansLibrary",
					"Scrivener" => "Scrivener",
					"Sculpt+" => "Sculpt+",
					"Sculptris" => "Sculptris",
					"ShaveandaHaircut" => "Shave and a Haircut",
					"ShiVa3D" => "ShiVa3D",
					"Shotgun" => "Shotgun",
					"Silo" => "Silo",
					"Silugen" => "Silugen",
					"Sketch" => "Sketch",
					"SketchApp" => "Sketch App",
					"SketchBookPro" => "SketchBook Pro",
					"SketchClub" => "SketchClub",
					"SketchUp" => "SketchUp",
					"Sketchable" => "Sketchable",
					"Sketchfab" => "Sketchfab",
					"Skyshop" => "Skyshop",
					"Snapseed" => "Snapseed",
					"Snowdrop" => "Snowdrop",
					"Softimage" => "Softimage",
					"SolidWorks" => "SolidWorks",
					"SonySketch" => "Sony Sketch",
					"Soundbooth" => "Soundbooth",
					"Source2" => "Source 2",
					"SourceControl" => "Source Control",
					"SourceFilmmaker" => "Source Filmmaker",
					"SpeedTree" => "SpeedTree",
					"Speedgrade" => "Speedgrade",
					"SpeedyPainter" => "SpeedyPainter",
					"Spine2D" => "Spine 2D",
					"Spriter" => "Spriter",
					"Stingray" => "Stingray",
					"Storyboarder" => "Storyboarder",
					"StoryboardPro" => "Storyboard Pro",
					"SublimeText" => "Sublime Text",
					"Substance3DDesigner" => "Substance 3D Designer",
					"Substance3DModeler" => "Substance 3D Modeler",
					"Substance3DPainter" => "Substance 3D Painter",
					"Substance3DSampler" => "Substance 3D Sampler",
					"Substance3DStager" => "Substance 3D Stager",
					"SubstanceB2M" => "Substance B2M",
					"SweetHome3D" => "Sweet Home 3D",
					"SynthEyes" => "SynthEyes",
					"TTools" => "TTools",
					"TVPaint" => "TVPaint",
					"TVPaintAnimation" => "TVPaint Animation",
					"TayasuiSketches" => "Tayasui Sketches",
					"TayasuiSketchesMobileApp" => "Tayasui Sketches Mobile App",
					"TayasuiSketchesPro" => "Tayasui Sketches Pro",
					"Terragen" => "Terragen",
					"Texturescom" => "Textures.com",
					"Texturingxyz" => "Texturingxyz",
					"TeyaConceptor" => "Teya Conceptor",
					"TheGrove3D" => "The Grove 3D",
					"TheaRender" => "Thea Render",
					"Threejs" => "Three.js",
					"Tiled" => "Tiled",
					"TiltBrush" => "Tilt Brush",
					"Tooll3" => "Tooll3",
					"ToonBoomHarmony" => "Toon Boom Harmony",
					"ToonBoomStudio" => "Toon Boom Studio",
					"ToonSquid" => "ToonSquid",
					"TopoGun" => "TopoGun",
					"TuxPaint" => "Tux Paint",
					"Tvori" => "Tvori",
					"Twinmotion" => "Twinmotion",
					"UNIGINEEngine" => "UNIGINE Engine",
					"UVLayout" => "UVLayout",
					"UltraFractal" => "Ultra Fractal",
					"uMake" => "uMake",
					"Unfold3D" => "Unfold 3D",
					"Unity" => "Unity",
					"UnrealEngine" => "Unreal Engine",
					"Vengi" => "vengi",
					"VRay" => "V-Ray",
					"VRED" => "VRED",
					"VTubeStudio" => "VTube Studio",
					"Vectary" => "Vectary",
					"VectorayGen" => "VectorayGen",
					"Vectorworks" => "Vectorworks",
					"VegasPro" => "Vegas Pro",
					"VisualDesigner3D" => "Visual Designer 3D",
					"VisualStudio" => "Visual Studio",
					"VRoidStudio" => "VRoid Studio",
					"Vue" => "Vue",
					"Vuforia" => "Vuforia",
					"WebGL" => "WebGL",
					"WhiteboardFox" => "Whiteboard Fox",
					"WickEditor" => "Wick Editor",
					"Wings3D" => "Wings 3D",
					"Word" => "Word",
					"WorldCreator" => "World Creator",
					"WorldMachine" => "World Machine",
					"XParticles" => "X-Particles",
					"Xfrog" => "Xfrog",
					"Xgen" => "Xgen",
					"xNormal" => "xNormal",
					"xTex" => "xTex",
					"XoliulShader" => "Xoliul Shader",
					"Yafaray" => "Yafaray",
					"Yeti" => "Yeti",
					"ZBrush" => "ZBrush",
					"ZBrushCore" => "ZBrushCore",
					"ZenBrush" => "Zen Brush"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $search){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: application/json, text/plain, */*",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			//"sentry-trace: 72b0318a7141fe18cbacbd905572eddf-a60de161b66b1e6f-1
			//"baggage: sentry-environment=vercel-production,sentry-release=251ff5179b4de94974f36d9b8659a487bbb8a819,sentry-public_key=2b87af2b44c84643a011838ad097735f,sentry-trace_id=72b0318a7141fe18cbacbd905572eddf,sentry-transaction=GET%20%2Fsearch,sentry-sampled=true,sentry-sample_rand=0.09967130764937493,sentry-sample_rate=0.5",
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			//"Referer: https://cara.app/search?q=jak+and+daxter&type=&sortBy=Top&filters=%7B%7D",
			"Referer: https://cara.app/search?q=" . urlencode($search),
			//"Cookie: __Host-next-auth.csrf-token=b752c4296375bccb7b480ff010e1e916c65c35c311a4a57ac6cd871468730578%7C4d3783cfb72a98f390e534abd149806432b6cf8d50555a52d00e99216a516911; __Secure-next-auth.callback-url=https%3A%2F%2Fcara.app; crumb=BV0HDt87G5+fOWE0ZDQ5MWM0ZTQ3YTZmMzM4MGU5MGNjNDNmMzY2",
			"Sec-Fetch-Dest: empty",
			"Sec-Fetch-Mode: cors",
			"Sec-Fetch-Site: same-origin",
			"TE: trailers"]
		);
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);

		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		return $data;
	}
	
	public function image($get){
		
		if($get["npt"]){
			
			[$npt, $proxy] =
				$this->backend->get(
					$get["npt"],
					"images"
				);
			
			$npt = json_decode($npt, true);
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
						
			$npt = [
				"q" => $get["s"],
				"sortBy" => $get["sort"],
				"take" => 24,
				"skip" => 0,
				"filters" => []
			];
			
			// parse filters
			if($get["type"] != "any"){
				
				$npt["filters"]["posts"] = [$get["type"]];
			}
			
			if($get["fields"] != "any"){
				
				$npt["filters"]["fields"] = [$get["fields"]];
			}
			
			if($get["category"] != "any"){
				
				$npt["filters"]["categories"] = [$get["category"]];
			}
			
			if($get["software"] != "any"){
				
				$npt["filters"]["softwares"] = [$get["software"]];
			}
			
			if($npt["filters"] == []){
				
				$npt["filters"] = "{}";
			}else{
				
				$npt["filters"] = json_encode($npt["filters"]);
			}
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		// https://cara.app/api/search/portfolio-posts?q=jak+and+daxter&sortBy=Top&take=24&skip=0&filters=%7B%7D
		try{
			$json =
				$this->get(
					$proxy,
					"https://cara.app/api/search/posts",
					$npt,
					$npt["q"]
				);
			
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		$imagecount = 0;
		foreach($json as $image){
			
			if(count($image["images"]) === 0){
				
				// sometimes the api returns no images for an object
				$imagecount++;
				continue;
			}
			
			$cover = null;
			$sources = [];
			
			foreach($image["images"] as $source){
				
				if($source["isCoverImg"]){
					
					$cover = [
						"url" => "https://images.cara.app/" . $this->fix_url($source["src"]),
						"width" => 500,
						"height" => 500
					];
				}else{
					
					$sources[] = [
						"url" => "https://images.cara.app/" . $this->fix_url($source["src"]),
						"width" => null,
						"height" => null
					];
				}
			}
			
			if($cover !== null){
				
				$sources[] = $cover;
			}
			
			$out["image"][] = [
				"title" => str_replace("\n", " ", $image["content"]),
				"source" => $sources,
				"url" => "https://cara.app/post/" . $image["id"]
			];
			
			$imagecount++;
		}
		
		if($imagecount === 24){
			
			$npt["skip"] += 24;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($npt),
					"images",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function fix_url($url){
		
		return
			str_replace(
				[" "],
				["%20"],
				$url
			);
	}
}
