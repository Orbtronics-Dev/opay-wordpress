{
  description = "Opay Wordpress Plugin";

  inputs = {
    flake-utils = { url = "github:numtide/flake-utils"; };

    nixpkgs = { url = "github:nixos/nixpkgs/nixpkgs-unstable"; };
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs {
          inherit system;

          config = { allowUnfree = true; };
        };
      in {
				devShells = {
					default = pkgs.mkShell {
						packages = with pkgs; [
							claude-code
						];
					};
				};
      });
}
